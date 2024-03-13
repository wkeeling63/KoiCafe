#include <linux/fs.h>
#include <linux/cdev.h>
#include <linux/pwm.h>
#include <linux/workqueue.h>
#include <linux/delay.h>
#include <linux/platform_device.h>
#include <linux/of.h>

/* Meta Information */
MODULE_LICENSE("GPL");
MODULE_AUTHOR("William Keeling");
MODULE_DESCRIPTION("PWM driver for fish feeder");

/* Variables for device and device class */
static dev_t pwm_device_num;
static struct class *pwm_class;
static struct cdev char_device;
struct pwm_device *pwm0 = NULL;
/* period_time of 10ms=100hz 100ms=10hz */
int period_time=50;
int start_time=250;

#define NSMULTIPLE 1000000
#define DRIVER_NAME "pwm_driver"
#define DRIVER_CLASS "PWMClass"


struct buff {
unsigned char time;
unsigned char dc;
unsigned char check;
};

struct queue_work {
int msecs;
int on_time;
struct work_struct pwm_work;
} work_data;
 
/* write from user space, validate request and queue request if not currently running */
static ssize_t driver_write(struct file *File, const char *user_buffer, size_t count, loff_t *offs) 
{
	static int in_use=0;
	int status=0;
	struct buff in;
	
	if (in_use) return in_use;
	
	in_use=-1;
	if (count != sizeof(in)) {
		printk("PWM driver: Invalid request length\n");
		status=-2; 
		goto skip_request;
	}
	if (copy_from_user(&in, user_buffer, 3) != 0) {
		printk("PWM driver: Copy from user space failed\n");
		status=-2; 
		goto skip_request;
	}
	if ((unsigned char)~(in.time+in.dc+in.check)) {
		printk("PWM driver: Invalid checksum\n");
		status=-2; 
		goto skip_request;
	}
	if ((in.time < 1) || (in.time > 60)) {
		printk("PWM driver: Invalid time\n");
		status=-2; 
		goto skip_request;
	}
	if ((in.dc <= 0) || (in.dc > 100)) {
		printk("PWM driver: Invalid duty cycle\n");
		status=-2; 
		goto skip_request;
	}
		
	work_data.on_time = (in.dc) ? (period_time*in.dc/100) : 0;
	work_data.msecs = in.time*1000;
	
	if (!(work_busy(&work_data.pwm_work))) {
		schedule_work(&work_data.pwm_work);
		in_use = 0;
		return count;
	} else {
		status =-1;
	}
	
skip_request:
	in_use=0;
	return status;
} 
/* set device file permission for char device */
static char *pwm_devnode(struct device *dev, umode_t *mode)
{
	if (mode) *mode = 0666;
	return NULL;
}
/* file operations structure for char device */
static struct file_operations fops = {
	.owner = THIS_MODULE,
	.write = driver_write
};
/* scheduled request to run feeder without waiting to return to userspace */
static void run_feeder(struct work_struct *work)
{
	struct queue_work *data = container_of(work,struct queue_work, pwm_work);
	int msecs = data->msecs-start_time;
  	if (pwm_config(pwm0, period_time*NSMULTIPLE, period_time*NSMULTIPLE)) 
		printk("PWM driver: config failed (full)\n");
	msleep(start_time);
	if (pwm_config(pwm0, data->on_time*NSMULTIPLE, period_time*NSMULTIPLE)) 
		printk("PWM driver: config failed (run)\n");
	msleep(msecs);
	if (pwm_config(pwm0, 0, period_time*NSMULTIPLE)) 
		printk("PWM driver: config failed (off)\n");
	return;
}

/* install platform driver fuction */
static int platform_probe(struct platform_device *pdev) {
	
	struct device_node *of_node = NULL;
	u32 parms[3];
	int status=0;
	
	printk("PWM driver: Installing\n");
	
	/* get parameter from device tree overlay */
	of_node = of_find_node_by_name( NULL, "williespwm");
	status = of_property_read_u32_array(of_node, "willies_parms", parms, 2);
	if (status) {
		printk("PWM driver: Defaulting period (10ms) and start time (250ms)\n");
	} else {
		period_time = parms[0];
		start_time = parms[1];
		printk("PWM driver: Period is %dms and motor start 100%% time %dms\n", parms[0], parms[1]);
	}
	of_node_put(of_node);
	
	/* get PWM child if platform device of device tree overlay 
	 * no need to use pwm_put and devm_pwm_get will do it at part of platform device removal*/
    pwm0 = devm_pwm_get(&pdev->dev, NULL); 
	if (IS_ERR_OR_NULL(pwm0)) {
		printk("PWM driver: Could not get PWM0! %ld\n", PTR_ERR(pwm0));
		if (pwm0) return -EPROBE_DEFER;
		printk("PWM driver: Non recoverable error on get PWM0!\n");
		return -1;
	} 
	
    /* PWM initial state*/
   	if (pwm_config(pwm0, 0, period_time*NSMULTIPLE)) printk("PWM driver: config failed (init)\n");
	if (pwm_enable(pwm0)) printk("PWM driver: enable failed\n");
	
	/* initialize work schedule structure and function */
	INIT_WORK(&work_data.pwm_work, run_feeder);

	/* create character device for userspace interface */
	if( alloc_chrdev_region(&pwm_device_num, 0, 1, DRIVER_NAME) < 0) {
		printk("PWM driver: Device number not allocated!\n");
		return -1;
	}
	printk("PWM driver: Device Major: %d, Minor: %d\n", 
		pwm_device_num >> 20, pwm_device_num && 0xfffff);

	if((pwm_class = class_create(THIS_MODULE, DRIVER_CLASS)) == NULL) {
		printk("PWM driver: Class not created!\n");
		goto ClassError;
	}
	pwm_class->devnode = pwm_devnode;

	if (device_create(pwm_class, NULL, pwm_device_num, NULL, DRIVER_NAME) == NULL) {
		printk("PWM driver: Can not create device file!\n");
		goto FileError;
	}

	cdev_init(&char_device, &fops);

	if(cdev_add(&char_device, pwm_device_num, 1) == -1) {
		printk("PWM driver: Registering device to kernel failed!\n");
		goto AddError;
	}

	printk("PWM driver: Installed\n");
	return 0;
AddError:
	device_destroy(pwm_class, pwm_device_num);
FileError:
	class_destroy(pwm_class);
ClassError:
	unregister_chrdev_region(pwm_device_num, 1);
	return -1;
}
/* remove platform driver fuction */ 
static int platform_remove(struct platform_device *pdev) {
	cdev_del(&char_device);
	device_destroy(pwm_class, pwm_device_num);
	class_destroy(pwm_class);
	unregister_chrdev_region(pwm_device_num, 1);
	printk("PWM driver: Removed\n");
	return 0;
}

/* setup device tree/open firmware table for compatible matching 
 * to load this module when device tree overlay is initailized */
static struct of_device_id my_match_table[] = {
     {
             .compatible = "williespwm",
     },
     {},
};
/* expose the above as a module alais so module is found and loaded */
MODULE_DEVICE_TABLE(of, my_match_table);

/* setup platform functions (probe and remove) and match table */
static struct platform_driver platform_driver = {
    .probe = platform_probe,
    .remove = platform_remove,
    .driver = {
        .name = "williesdriver",
        .owner = THIS_MODULE,
        .of_match_table = of_match_ptr(my_match_table),
    },
};
/* expose platform struct so compatible matching can run fuctions at
 * platform device creation and destruction    */
module_platform_driver(platform_driver);

