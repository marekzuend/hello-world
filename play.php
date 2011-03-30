<?php

require_once('libimob.php');


#$db = new mysqli('localhost', 'lol0', 'dongs', 'dongllah');
require_once('db.php');

function updateUser($v) {
    global $db, $imob;

    //check to make sure its another player
    if($v['Profile'] == 'profile') return;

    $do_update_user = true;
    
    $sql = sprintf("SELECT `LastEdited` FROM `Player` WHERE `ProfileID` = '%d' LIMIT 1", (int)$v['ProfileID']);
    $result = $db->query($sql) or $imob->Log($db->error, 'W');
    if($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if(date('U') - strtotime($row['LastEdited'] < 79800)) { // at least 1 day
            $users[] = $v['ProfileID'];
            $result->free();
            $do_update_user = false;
        }
    }
    if($do_update_user) {
        $user = $imob->UserInfo($v['Profile']);
        $sql = sprintf("REPLACE INTO `Player` (`ClassName`, `LastEdited`, `UserName`, `Level`, `Class`, `Game`, `ProfileID`) VALUES".
                "('Player', NOW(), '%s', '%d', '%s', 'iMobsters', '%d');", $db->real_escape_string($user['Name']),
                (int)$user['Level'], $db->real_escape_string($user['Class']), (int)$user['ProfileID']);
        $db->query($sql) or $imob->Log($db->error, 'W');
        return $user;
    }
    return $v;
}


if($imob) { //we haz ignition, rage engage.

    while(1) {
        //reset some loopy stuff
        $users = array();
        $energysleeptil = (isset($energysleeptil)) ? $energysleeptil : 0;

        if($energysleeptil < date('U')) {
            //do some missions to progress
            do {
                $mission = $imob->MissionBestExp(true);
                $imob->DoMission($mission);
            } while($imob->energy > 0 && $mission !== false);

            //do some missions for xp
            while($imob->energy > 0) {
                $imob->DoMission($imob->MissionBestExp());
            }
        }

        $imob->Heal();

        //fight for some cash
        while($imob->stamina > 0 && $imob->health > 30) {
            $t = $imob->Targets();
            foreach($t as $k => $v) {
            if(in_array($v['ProfileID'], $users)) continue;
                $u = updateUser($v);
                $users[] = $u['ProfileID'];
            }
            $imob->Fight($t[0]);
        }

        //spend that cash
        do {
            $buy = $imob->TopRealEstate(true, true);
        } while ($buy !== false);

        //see if its time to spend some more cash -- fuck it buggy

/*        $bb = $imob->BankBalance();
        $total = $bb + $imob->cash;
  
        $imob->Log(sprintf('cash: %s bank: %s total %s', $imob->cash, $bb, $total), 'I');

        $tre = $imob->TopRealEstate(true, false, $total);

        if($tre) { 
            $imob->Log(sprintf('Target Buy: %s for %s', $tre['Name'], $tre['NewCost']), 'I');
            if($total >= $tre['NewCost']) {
                $imob->Log('Total is more than Cost', 'I');
                if($tre['NewCost'] > $imob->cash) {
                    $need = $tre['NewCost'] - $imob->cash;
                    $imob->Log(sprintf('Need to make a withdrawal of %s', $need), 'I');
                    $imob->BankWithdraw($need);
                }
                $imob->TopRealEstate(true, true);
                continue;
            } 
        }
*/

        //crawl some comments and profiles
       $skip_comments = false;            

       foreach($imob->Comments() as $k => $v) {
            if($v['Profile'] == 'profile') continue; //fuck that its user posting to them self
            if(in_array($v['ProfileID'], $users)) continue;
            
            $u = updateUser($v);
            $users[] = $u['ProfileID'];

            if(!$skip_comments) {

                $sql = sprintf("SELECT `ID` FROM `GameComment` WHERE `FromID` = '%s' AND `Text` = '%s' LIMIT 1", (int)$v['ProfileID'], $db->real_escape_string($v['Comment']));
                $result = $db->query($sql) or $imob->Log($db->error, 'W');
                if($result->num_rows == 0) {
                    $sql = sprintf("INSERT INTO `GameComment` (`ClassName`, `Created`, `LastEdited`, `Text`, `FromID`) VALUES ('GameComment', NOW(), NOW(), '%s', '%d');", 
                       $db->real_escape_string($v['Comment']), (int)$v['ProfileID']);
                    $db->query($sql) or $imob->Log($db->error, 'W');
                } else {
                    //assume this shit is old and skip moar inserts
//                    $skip_comments = true;
                }
                $result->free();
            }
        }

        foreach($imob->Fights() as $k => $v) {
            if(in_array($v['ProfileID'], $users)) continue;
            $u = updateUser($v);
            $users[] = $u['ProfileID'];
        }

        $imob->RespondToInvites();

        //see if it reall is time to nap or can we keep playing?
        if($imob->energy > 0 || ($imob->health > 30 && $imob->stamina > 0)) continue;

        $imob->BankDeposit();

        //srsly the timing is luls when this shit happens!
        if($imob->cash > 0) continue;

#       $imob->BankWithdraw(10);

        /* 
         * figure out exact sleep times here to maximize efficiency
         */


        //sleep my child
        $now = date('U');

        //nasty estimate for now
        $energysleeptil = $imob->maxenergy * 240 + $now;
        $sleeptil = $now + 7200;

#        $imob->Log(sprintf('Sleeping for 1 hour (until %s)', date('d-M-Y G:i:s', $sleeptil)), 'N');
        $si=0;
        echo "\n";
        while($sleeptil > $now) {
            $now = date('U');
            $min = ($sleeptil-$now)/60;
            $energymin = ($energysleeptil-$now)/60;
            $s = array('-', '\\', '|', '/');
            if($si == 4) $si = 0;
            $sym = $s[$si++];
            printf("\r[%s] Notice: Sleeping for %d minutes (until %s), energy restored in %d minutes", $sym, $min, date('d-M-Y G:i:s', $sleeptil), $energymin);
            sleep(2);
        }
        echo "\n";
        $imob->Auth();

    }
}


#var_dump($imob);
