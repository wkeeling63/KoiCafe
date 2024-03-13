<html>
<head>
<title>KoiCafe</title>
<style>
h1, h3 {
  text-align: center;
}
table, th, td {
  border: 1px solid black;
  border-collapse: collapse;
  text-align: center;
  vertical-align: middle;
}
table.center {
  margin-left: auto; 
  margin-right: auto;
}
form.none {
    margin-bottom: 0px;
}
div.form-center {
    margin-left: auto; 
    margin-right: auto;
    width: 80%;
}
input.inerror {
    background-color: red;
    font-weight: bold;
}
.grid-container {
    display: grid;
    margin-left: auto; 
    margin-right: auto;
    width: 80%;
    grid-template-columns: auto auto;
    column-gap: 50px;
}
</style>
</head>
<body>
<?php 
$ehour_op=NULL;
$emin_op=NULL;
$emon_op=NULL;
$eday_op=NULL;
$edow_op=NULL;

function is_valid_cron(string $entry, int $min, int $max) {
	# is entry only *
	if (strpos($entry, "*") !== false) {
		if (strlen($entry) == 1) {
			return true;}
		else {
			return false;}
	}
	$parsed=explode(",",$entry);
	foreach($parsed as $row => $value) {
		# if row is range 
		if (strpos($value, "-") !== false) {
			$parsed[$row] = explode("-", $value);
		if (sizeof($parsed[$row]) !== 2) {
			return false;}
		foreach($parsed[$row] as $rrow => $rvalue) {
			if (!(is_numeric($rvalue))) {
				return false;}
			if ($rvalue < $min || $rvalue > $max) {
				return false;}
		}
		$range = $parsed[$row];
		if ($range[0] >= $range[1]) {
			return false;}
		}
		# if row is single value
		if (!(is_numeric($value)) && $value < $min || $value > $max) {
			return false;}
	}
return true;
}
function validate_entry() {
	global $ehour_op, $emin_op, $emon_op, $eday_op, $edow_op;
	$errorcnt = 0;
#   $_POST['e_enabled']} {$_POST['e_minute']} {$_POST['e_hour']} {$_POST['e_day']} {$_POST['e_month']} {$_POST['e_dow']
	if (is_valid_cron($_POST['e_hour'], 0, 23) == false) {
		$ehour_op = 'class="inerror"';
		$errorcnt++;}
	if (is_valid_cron($_POST['e_minute'], 0, 59) == false) {
		$emin_op = 'class="inerror"';
		$errorcnt++;}
	if (is_valid_cron($_POST['e_month'], 1, 12) == false) {
		$emon_op = 'class="inerror"';
		$errorcnt;}		
	if (is_valid_cron($_POST['e_day'], 1, 31) == false) {
		$eday_op = 'class="inerror"';
		$errorcnt++;}
	if (is_valid_cron($_POST['e_dow'], 0, 6) == false) {
		$edow_op = 'class="inerror"';
		$errorcnt++;}
	if ($errorcnt == 0) return true; else return false;
}
function replace_crontab(array &$new_cron) {
    $tmpfname = tempnam("/tmp", "crontmp");
    $new_cron_str = implode("\n", $new_cron) . "\n";
    file_put_contents($tmpfname, $new_cron_str);
    exec("crontab  $tmpfname", $crontab, $retval);
    unlink($tmpfname);
    return "OK";
}
$crontab=null; $retval=null;
        exec('crontab  -l', $crontab, $retval);
        $cron_size = sizeof($crontab);
# feed_button 
if (isset($_POST['feed_button'])) {
    exec("feedfish $_POST[fseconds] $_POST[fintensity]", $foutput, $frc);
    if ($frc != 0) { 
        $fsecs=$_POST[fseconds];
        $fint=$_POST[fintensity];
        if ($frc == 1) {
            $fmessage = 'Feeder is busy!';
        } else {
            $fmessage = 'Invalid option!';
        }
    } else {
        $fsecs=$fint=$fmessage=NULL;
    }
}
# row_button
if (isset($_POST['row_button'])) {
	$s_row = $_REQUEST['selected_row'];
    list($e_feedint, $e_feedsec,, $e_dow, $e_month, $e_day, $e_hour, $e_minute, $e_enabled) = array_reverse(explode(" ",trim($crontab[$s_row])));
} 
# where to init add form if 
#else {
#    $e_minute = $e_hour = $e_day = $e_month = $e_dow = '*'; $e_pgm = 'feedfish'; $e_feedsec = $e_feedint = 5; $e_enabled = ''; $s_row=sizeof($crontab);
#}
# add_button 
if (isset($_POST['add_button'])) {
    if (validate_entry() != false) {
        $crontab[] = "{$_POST['e_enabled']} {$_POST['e_minute']} {$_POST['e_hour']} {$_POST['e_day']} {$_POST['e_month']} {$_POST['e_dow']} feedfish {$_POST['e_feedsec']} {$_POST['e_feedint']}";
        replace_crontab($crontab);
    } else {
        $e_hour=$_POST['e_hour'];
        $e_minute=$_POST['e_minute'];
        $e_month=$_POST['e_month'];
        $e_day=$_POST['e_day'];
        $e_dow=$_POST['e_dow'];
        $e_feedsec=$_POST['e_feedsec'];
        $e_feedint=$_POST['e_feedint'];
    }
}
# save_button
if (isset($_POST['save_button'])) {
    $s_row = $_REQUEST['selected_row'];
    if (validate_entry() != false) {
#    echo "$s_row";
        $crontab[$s_row] = "{$_POST['e_enabled']} {$_POST['e_minute']} {$_POST['e_hour']} {$_POST['e_day']} {$_POST['e_month']} {$_POST['e_dow']} feedfish {$_POST['e_feedsec']} {$_POST['e_feedint']}";
        replace_crontab($crontab);
    } else {
        $e_hour=$_POST['e_hour'];
        $e_minute=$_POST['e_minute'];
        $e_month=$_POST['e_month'];
        $e_day=$_POST['e_day'];
        $e_dow=$_POST['e_dow'];
        $e_feedsec=$_POST['e_feedsec'];
        $e_feedint=$_POST['e_feedint']; 
        $saveerror='YES';   
    }
    
}
# delete_button 
if (isset($_POST['delete_button'])) {
    $s_row = $_REQUEST['selected_row'];
    array_splice($crontab, $s_row, 1);
    replace_crontab($crontab);
}
# cancel_button -- no logic needed for cancel just do nothing
?>

<?php 
#print_r($_REQUEST);
#echo '$_REQUEST<br>';
#print_r($_POST); 
#echo '$_POST<br>';
#print_r($crontab);
#echo '$crontab<br>';
?>
<h1>Willie's KoiCafe Controller</h1><h3><?php echo date("M j, Y g:i A"); ?></h3><br>
<div class="form-center">  
<form action="koicafe.php" method="post">
<input type="submit" name=feed_button value="Feed Now"> for 
<input type="number" name="fseconds" min="1" max="60" maxlength="2" id="fsecs" value= "<?php echo $fsecs; ?>" size=4 required>  seconds at 
<input type="number" name="fintensity" min="1" max="10" maxlength="2" id="fint" value= "<?php echo $fint; ?>" size=4 required>  intensity.
<b><font color='red'><?php echo "$fmessage"; ?></font><b/>
</form>
</div>
<div class="grid-container">
<div>
<h4>Koi Cafe event entry</h4>
<form action="koicafe.php" method="post"> 
<input type="radio" id="enable" name="e_enabled" value="" <?php  if ($e_enabled != "#") echo 'checked'; ?>>
<label for="enable">Enable</label>
<input type="radio" id="disable" name="e_enabled" value="#" <?php if ($e_enabled == "#") echo 'checked'; ?>>
<label for="disable">Disable</label><br>When to feed:<br>
<input type="text" <?php echo $ehour_op; ?> name="e_hour" id="ehour" value="<?php echo $e_hour; ?>" size=15 required> hours 0-23<br>  
<input type="text" <?php echo $emin_op; ?> name="e_minute" id="emin" value="<?php echo $e_minute; ?>" size=15 required> minutes 0-59<br>
<input type="text" <?php echo $emon_op; ?> name="e_month" id="emon" value="<?php echo $e_month; ?>" size=15 required> months 1-12<br> 
<input type="text" <?php echo $eday_op; ?> name="e_day" id="eday" value="<?php echo $e_day; ?>" size=15 required> days of the month 1-31<br>
<input type="text" <?php echo $edow_op; ?> name="e_dow" id="edow" value="<?php echo $e_dow; ?>" size=15 required> days of the week 0-6 Sun=0<br>How to feed:<br>
<input type="number" name="e_feedsec" id="esec" min="1" max="60" maxlength="2" value="<?php echo $e_feedsec; ?>" required> seconds to feed 1-60<br> 
<input type="number" name="e_feedint" id="eint" min="1" max="10" maxlength="2" value="<?php echo $e_feedint; ?>" required> intensity to feed 1-10<br>
<input type="hidden" name="selected_row" value=<?php echo $s_row?>>
<?php 
if (isset($_POST['row_button']) || isset($saveerror)) {
    echo '<input type="submit" name="save_button" value="Save">';
    echo '<input type="submit" name="delete_button" value="Delete">';
    echo '<input type="submit" name="cancel_button" value="Cancel">';
} else {
    echo '<input type="submit" name="add_button" value="Add">';
}
?>
</form>
</div>
<div><p>The feeding entry has 2 parts “When to feed” (when the event triggers) and “How to feed” (how long and what intensity 
to feed for event). When is made up of hour, minute, month, day and these all must be true for the event to trigger.  Both ways to specify the day 
are treated as one; in other word if either day of month or day of week are true the day is true. When types can contain many values 
separated by commas (,) and the values can be individual values or ranges. Ranges are 2 values with a dash between (4-6). Values, individual or 
ranges, are only validated for being with in the range of the type for example day of the month must be 1-31 but this does not check if the month 
has a 31st.  Ranges must have a lower value before a higher value.  If the type, hour for example, is to be true of all values an asterisk (*) should 
be use instead of list all values or a range of all values, but all 3 formats are.  If an asterisk is used it can be the only value.  How  is made of 
2 values the number of seconds to feed and the intensity of feeding.  You could specify an asterisk for all when types and 60 seconds for feed time 
while this is valid it is illogical as it would result in feed every minute of every hour of every day of every month for a minute at a time; so, 
continuous feeding. You can have many feedings event that differ in any way, for example you can have an event that feed year-round (* in months) 
and others that feed only in the warm months.</p></div></div>
<div class="table-responsive">
<div align="center">
</div>
    <table class="center" style="width:80%">
        <thead>
            <tr>
                <th>Select row</th>
                <th>Enabled</th>
                <th>Hours</th>
                <th>Minutes</th>
                <th>Months</th>
                <th>Days of Month</th>
                <th>Days of Week</th>
                <th>Feed seconds</th>
                <th>Feed intensity</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($crontab as $cron_row => $event) { ?>
                <?php list($feedint, $feedsec,, $dow, $month, $day, $hour, $minute, $comment) = array_reverse(explode(" ",trim($event)));
#                print_r($comment); echo '<br>';
                if ($comment == '#')  $enabled = 'No';  else $enabled = 'Yes'; 
 ?>
                <tr>
                    <td><form class="none" action="koicafe.php" method="post"><input type="submit" name=row_button value="Select"><input type="hidden" name="selected_row" value=<?php echo "$cron_row"?>></form></td>
                    <td><?php echo $enabled ?></td>           
                    <td><?php echo $hour ?></td>
                    <td><?php echo $minute ?></td>
                    <td><?php echo $month ?></td>
                    <td><?php echo $day ?></td>
                    <td><?php echo $dow ?></td>
                    <td><?php echo $feedsec ?></td>
                    <td><?php echo $feedint ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
<script type="text/javascript">
var numRegExp = new RegExp('\\d');
var cronRegExp = new RegExp('\\d|\\*|\\,|-');
document.getElementById('fsecs').addEventListener('keydown', function(event) {
    if(event.ctrlKey || event.altKey || typeof event.key !== 'string' || event.key.length !== 1) {
        return;}
    if(!numRegExp.test(event.key)) {
        event.preventDefault();}}, false);
document.getElementById('fint').addEventListener('keydown', function(event) {
    if(event.ctrlKey || event.altKey || typeof event.key !== 'string' || event.key.length !== 1) {
        return;}
    if(!numRegExp.test(event.key)) {
        event.preventDefault();}}, false);
document.getElementById('ehour').addEventListener('keydown', function(event) {
    if(event.ctrlKey || event.altKey || typeof event.key !== 'string' || event.key.length !== 1) {
        return;}
    if(!cronRegExp.test(event.key)) {
        event.preventDefault();}}, false);
document.getElementById('emin').addEventListener('keydown', function(event) {
    if(event.ctrlKey || event.altKey || typeof event.key !== 'string' || event.key.length !== 1) {
        return;}
    if(!cronRegExp.test(event.key)) {
        event.preventDefault();}}, false);
document.getElementById('emon').addEventListener('keydown', function(event) {
    if(event.ctrlKey || event.altKey || typeof event.key !== 'string' || event.key.length !== 1) {
        return;}
    if(!cronRegExp.test(event.key)) {
        event.preventDefault();}}, false);
document.getElementById('eday').addEventListener('keydown', function(event) {
    if(event.ctrlKey || event.altKey || typeof event.key !== 'string' || event.key.length !== 1) {
        return;}
    if(!cronRegExp.test(event.key)) {
        event.preventDefault();}}, false);
document.getElementById('edow').addEventListener('keydown', function(event) {
    if(event.ctrlKey || event.altKey || typeof event.key !== 'string' || event.key.length !== 1) {
        return;}
    if(!cronRegExp.test(event.key)) {
        event.preventDefault();}}, false);
document.getElementById('esec').addEventListener('keydown', function(event) {
    if(event.ctrlKey || event.altKey || typeof event.key !== 'string' || event.key.length !== 1) {
        return;}
    if(!numRegExp.test(event.key)) {
        event.preventDefault();}}, false);
document.getElementById('eint').addEventListener('keydown', function(event) {
    if(event.ctrlKey || event.altKey || typeof event.key !== 'string' || event.key.length !== 1) {
        return;}
    if(!numRegExp.test(event.key)) {
        event.preventDefault();}}, false);
</script>

</body>
</html>
