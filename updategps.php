<?php
/**
 * Created by JetBrains PhpStorm.
 * User: graher01
 * Date: 3/26/13
 * Time: 12:09 PM
 * To change this template use File | Settings | File Templates.
 */
    // Variable being filled by parameters sent from VDL.
    $unit_id	= $_REQUEST['unitid'];
    $sqlInsert 	= $_REQUEST['insertstr'];

    // Database information ie. username/password database name and ip or domain
    $db_host  = "localhost";
    $db_user = "admin";
    $db_pass = "gaisthebest";
    $database = "ga2";
    // Connect to mysql server
    $conn = mysql_connect($db_host,$db_user,$db_pass);

    // Connect to the database ga2
    $db = mysql_select_DB("$database", $conn)
                or die("Sorry!. I could not open database, Your internet must be down. check with your provider.");

    // Getting modem_id from the modem table to be use in gps_messages and stationary_periods
    // query build to get modem ID
    $getModemID = "SELECT * FROM `modems` WHERE  imei like '%".$unit_id."%'";
    $result = mysql_query($getModemID,$conn);
    $row = mysql_fetch_assoc($result);
    // testing ID if it it is 0
    $modems_id = 0;
    if ($row['id'] > 0){
        $modems_id= $row['id'];
    }
    // This section is processes the gps_messages table.
    // Processing query to insert record into gps_messages
    $processed = $sqlInsert.",".$modems_id.")";
    $rs=mysql_query($processed,$conn);
    $gps_id = mysql_insert_id ($conn);

    // This section processes the stationary_periods table
    // build query to get truck id from trucks_modems table.
    $query = "select truck_id from modems_trucks where modem_id=".$modems_id." and removed = '0000-00-00 00:00:00'";
    echo $query;
    $result = mysql_query($query);
    $numrows = mysql_num_rows($result);

    // if not record set is return we do nothing because truck does not exist.
    if ($numrows){
        $row = mysql_fetch_assoc($result);
        $trucks_id = $row['truck_id'];
        $query = "select * from stationary_periods where modem_id=".$modems_id." and truck_id=".$trucks_id." and complete=0";
        $result = mysql_query($query);
        $sp_nums = mysql_num_rows($result);

        // Here we check if there is record set in the stationary_periods for specific truck and stationary_period is not complete.
        // if record set has rows we update stationary_period otherwise we insert a new row to stationary_period table.
        if ($sp_nums){
            // then there is an ACTIVE stationary period for this modem/truck
            $gps_query = "select * from gps_messages where id=".$gps_id;
            $gps_result = mysql_query($gps_query);
            $gps_row = mysql_fetch_assoc($gps_result);


            // if truck is now moving update the stationary period and complete it
            if ($gps_row['calculated_speed'] > 5){
                   // then the truck is currently MOVING
                   // Was truck stationary in previous message
                    $sp_query = "UPDATE stationary_periods set end='".$gps_row['message_generated']."',complete=1 where complete = 0 and modem_id=".$modems_id." and truck_id=".$trucks_id;
                    $result = mysql_query($sp_query);
                    echo $sp_query;
            }else{
                $sp_query = "UPDATE stationary_periods set end='".$gps_row['message_generated']."' where complete = 0 and modem_id=".$modems_id." and truck_id=".$trucks_id;
                $result = mysql_query($sp_query);
            }

        }else{
            // The truck was NOT stationary as of the previous message
            // Get elements of CURRENT GPS Msg
            $gps_query = "select * from gps_messages where id=".$gps_id;
            $gps_result = mysql_query($gps_query);
            $gps_row = mysql_fetch_assoc($gps_result);


            if ($gps_row['calculated_speed'] < 5 ){
                // Then add a stationary period and fill in all fields - if > 5 then truck IS not stationary and WAS not stationary - do nothing
                $prevquery = "select * from gps_messages where modems_id=".$modems_id." order by message_generated desc LIMIT 2";
                $prevresult = mysql_query($prevquery);
                $prev =mysql_num_rows($prevresult);
                $prevrow = '';
                if ($prev){
                    $prevrow = mysql_fetch_assoc($prevresult);
                    $prevrow = mysql_fetch_assoc($prevresult);
                    $sp_query = "INSERT INTO `stationary_periods`(`modem_id`, `truck_id`, `longitude`, `latitude`, `start`, `end`)
                                            VALUES ($modems_id,$trucks_id,".$prevrow['longitude'].",".$prevrow['latitude'].",'".$prevrow['message_generated']."','".$gps_row['message_generated']."')";
                    $result = mysql_query($sp_query);
                }
                echo $sp_query."</br>";
                echo $prevquery."   ".$gps_row['calculated_speed']."    ".$prevrow['message_generated']."   ".$prev;
            }

        }

    }
    mysql_close($conn);
?>
