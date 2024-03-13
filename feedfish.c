/*
feedfish.c

gcc -o feedfish feedfish.c 
*/

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <fcntl.h>
#include <errno.h>

extern int errno ;

int main(int argc, char *argv[])
{
        int fd;
        char rc=-2;
        int intensity=0,status=0;
        int value[11] = {0,50,56,62,68,74,80,86,92,98,100};
        struct buff {
                unsigned char time;
                unsigned char dc;
                unsigned char check;
        } out_buff;

        if (argc != 3)  {
                printf("Invalid number of parameters %d\nUsage: feedfish SECONDS INTENSITY\nSECONDS 1-60 INTENSITY 1-10\n", argc);
                exit(2);
                }
        out_buff.time = atoi(argv[1]);
        if ((out_buff.time < 1) || (out_buff.time > 60)) {
                printf("Invalid number of secondsnUsage: feedfish SECONDS INTENSITY\nSECONDS 1-60 INTENSITY 1-10\n");
                exit(2);
                }
        intensity  = atoi(argv[2]);
        if ((intensity < 1) || (intensity > 10)) {
                printf("Invalid intensity nUsage: feedfish SECONDS INTENSITY\nSECONDS 1-60 INTENSITY 1-10\n");
                exit(2);
                }
        out_buff.dc = value[intensity]; 
        out_buff.check = ~(out_buff.time+out_buff.dc);
        
        fd = open("/dev/pwm_driver", O_WRONLY, O_SYNC);      
        if (write(fd, &out_buff, sizeof(out_buff)) >= 0) exit(0);
        exit(errno);

}
