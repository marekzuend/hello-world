
<?php


class iMobster {
    protected $ch; //curl handle
    protected $udid;
    protected $pf;
    protected $dt;

    var $debug = false;
    
    protected $menulinks = array(
        'bank' => false,
        'profile' => false,
        'favor' => false,
        'hospital' => false,
        'missions' => false,
        'fight' => false,
        'euiptment' => false,
        'investment' => false,
        'group' => false,
        'setting' => false,
        'hitlist' => false
    );

    var $url = 'http://im.storm8.com';
    var $platform;

    protected $referer = false;

    protected $logfile = 'imob.log';
    protected $lh; //log handle

    //playa nf0.
    public $name = '';

    public $mobsize = 0;
    public $mobcode = false;

    public $cash = 0;
    public $income = 0;
    public $exp = 0;
    public $levelexp = 0;
    public $level = 0;

    public $statsavail = false;

    public $health = 0;
    public $maxhealth = 0;

    public $energy = 0;
    public $maxenergy = 0;

    public $stamina = 0;
    public $maxstamina = 0;

    public $timeleft = 0;
    public $energyrate = 0;

    public $bankbalance = 0; //be sure to update with BankBalance()
    public $favorpoints = 0;

    //constructicons, form devestat0r!
    function __construct($udid, $pf, $platform = 'iphone', $debug_or_dt = false, $debug2 = false) {
        if(!$udid || !$pf) return false;
        $this->udid = $udid;
        $this->pf = $pf;

        $this->platform = $platform;

        if($platform == 'iphone' || $platform == 'android-pre' || $platform == 'iphone3') {
            $this->debug = $debug_or_dt;
        } else {
            $this->dt = $debug_or_dt;
            $this->debug = $debug2;
        }

        //open logfile
        $this->lh = fopen(sprintf("%s.%s", $this->logfile, $this->udid), 'a+');
        $this->Log(sprintf("Starting iMobsters %s", date('d-M-Y G:i:s')));

        //curl options
        $this->ch = curl_init();
        if($platform == 'android' || $platform == 'android-pre') {
            curl_setopt($this->ch, CURLOPT_HTTPHEADER, array('x-wap-profile: http://wap.samsungmobile.com/uaprof/GT-i9000.xml'));
            curl_setopt($this->ch, CURLOPT_USERAGENT, 'User-Agent: Mozilla/5.0 (Linux; U; Android 2.2; en-au; GT-I9000 Build/FROYO) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1');
        } elseif($platform == 'iphone') {
            curl_setopt($this->ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (iPhone; U; CPU iPhone OS 4_0_1 like Mac OS X; en-us) AppleWebKit/532.9 (KHTML, like Gecko) Mobile/8A306');
        } else {
            curl_setopt($this->ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (iPhone; U; CPU iPhone OS 3_1_3 like Mac OS X; en-us) AppleWebKit/528.18 (KHTML, like Gecko) Mobile/7E18');
	}
        
        $cookiejar = sprintf("cookiejar.%s", $this->udid);

        curl_setopt($this->ch, CURLOPT_COOKIEFILE, $cookiejar);
        curl_setopt($this->ch, CURLOPT_COOKIEJAR, $cookiejar);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 1);

        //debug only
        curl_setopt($this->ch, CURLOPT_HEADER, 1);

       if(!$this->Auth()) return false;

        $this->Go();
        $this->BankBalance();
    }

    function __destruct() {
        $this->Log("Exiting\n\n");
        fclose($this->lh);
    }

    function request($url, $method = 'GET', $data = false) {
        $this->Log(sprintf("%s: %s", $method, $url), 'D');

        $url = sprintf('%s/%s', $this->url, $url);
        curl_setopt($this->ch, CURLOPT_URL, $url);

        if($method == 'POST') {
            curl_setopt($this->ch, CURLOPT_POST, 1);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        if($this->referer) curl_setopt($this->ch, CURLOPT_REFERER, $this->referer);

        usleep(rand(400000,1500000));
        $ret = curl_exec($this->ch);

        if(($status = curl_getinfo($this->ch, CURLINFO_HTTP_CODE)) == 200 ) {
            $this->referer = curl_getinfo($this->ch, CURLINFO_EFFECTIVE_URL);
            $this->Log('OK!', 'D');
        } else {
            $this->Log(sprintf('status: %s body: %s', $status, $ret), 'D');
        }

#       var_dump($ret);
        //expire tokens, probably unnecessary
        foreach($this->menulinks as $k => $v) {
            $this->menulinks[$k] = false;
        }

        return array('body' => $ret, 'code' => $status, 'url' => $this->referer);
    }

    function OK($req) {
        if(strpos($req['body'], 'Error: Invalid access to this application') !== false) {
            $this->Log('Failed (incorrect nonce?)', 'E');
        }

        if($req['code'] != 200) {
            $this->Log(sprintf('Fail %s', $req['body']), 'E');
        }
 
        return true;
    }

    function Auth() {
        if($this->platform == 'android') {
           $req = $this->request(sprintf('aindex.php?version=a1.55&adid=%s&pf=%s&model=GT-I9000&sn=Android&sv=2.2&dt=%s', $this->udid, $this->pf, $this->dt));
        } elseif($this->platform == 'android-pre') {
            $req = $this->request(sprintf('aindex.php?version=a1.53&udid=%s&pf=%s&model=GT-I9000&sn=Android&sv=2.2', $this->udid, $this->pf));
        } elseif($this->platform == 'iphone') {
            $req = $this->request(sprintf('index.php?version=1.73&premium=true&udid=%s&pf=%s&model=iPhone&sv=4.0.1', $this->udid, $this->pf));
        } else {
            $req = $this->request(sprintf('index.php?version=1.73&premium=true&udid=%s&pf=%s&fpts=60&model=iPhone&sv=3.1.3', $this->udid, $this->pf));
	}
        if($this->OK($req)) {
            if(strstr($req['url'], 'ca_award_settlement')) {
                $req = $this->request('ca_award_settlement.php?action=Accept');
 /*               if($this->OK($req)) {
                    var_dump($req);
                } */
                //shit just 500's... scumbags, fuck em. return anyway.
                return true;
            } else return true;
        }
    }

    function Log($txt, $method = false) {
        $stdout = true;
        $nlog = false;
        switch($method) {
            case 'E':
                $method = 'Error';
                $sym = '[E]';
                break;
            case 'I':
                $method = 'Info';
                $sym = '[I]';
                break;
            case 'N':
                $method = 'Notice';
                $sym = '[-]';
                //$stdout = false;
                break;
            case 'D':
                $method = 'Debug';
                $sym = '[D]';
                $stdout = false;
                if(!$this->debug) $nlog = true;
                break;
            default:
                $sym = '[-]';
        }
        $log = sprintf("%s %s: %s\n", $sym, $method, $txt);
        if($stdout || $this->debug) echo $log;
        if(!$nlog) fwrite($this->lh, $log);
        if($method == 'Error') exit();
    }

    function UpdateStats($body) {
        if(!$body) return false;
 
        //name
        if(preg_match('/<div class="profileName"(.*?)>(.*?)<\/div>/', $body, $match)) {
            if($match[2] != $this->name) {
                $this->Log(sprintf('Name: %s', $match[2]), 'I');            
            }
            $this->name = $match[2];
        }

        //mobcode
        if(preg_match('/<span class="codeCode">(.*?)<\/span>/', $body, $match)) {
            if($match[1] != $this->mobcode) {
                $this->Log(sprintf('MobCode: %s', $match[1]), 'I');
            }
            $this->mobcode = $match[1];
        }       

        //mobsize
        if(preg_match('/<span class="crewCount">(.*?)<\/span>/', $body, $match)) {
            if($match[1] != $this->mobsize) {
                $this->Log(sprintf('MobSize: %d', $match[1]), 'I');
            }
            $this->mobsize = $match[1];
        }       
       
        //cash
        if(preg_match('/<span id="cashCurrent" style="white-space:nowrap">(.*?)<\/span>/', $body, $match)) {
            $cash = $this->launderMoney($match[1]);
            if($cash != $this->cash) {
                $diff = ($cash - $this->cash);
                $this->Log(sprintf('Cash: $%d (%d)', $cash, $diff), 'I');
            }
            $this->cash = $cash;
        }

        //income
        if(preg_match('/<div id="cashTimerDiv" class="cashBottomArea"><span(.*?)><span>(.*?)<span(.*?)>(.*?)<\/span><\/span> in/', $body, $match)) {
            $income = $this->launderMoney($match[2].$match[4]);
            if($income != $this->income) {
                $diff = ($income - $this->income);
                $this->Log(sprintf('Income: $%d p/h (%d)', $income, $diff), 'I');
            }
            $this->income = $income;
        }

        //exp / levelexp
        if(preg_match('/<span id="expText">(.*?)\/(.*?)<\/span>/', $body, $match)) {
            if($match[1] != $this->exp || $match[2] != $this->levelexp) {
                $this->Log(sprintf('Exp: %d/%d', $match[1], $match[2]), 'I');
            }
            $this->exp = $match[1];
            $this->levelexp = $match[2];
        }       

        //level
        if(preg_match('/<div class="levelFrontTopArea"><a(.*?)>(.*?)<\/a><\/div>/', $body, $match)) {
            if($match[2] != $this->level) {
                $this->Log(sprintf('Level: %d', $match[2]), 'I');
            }
            $this->level = (int)$match[2];
            if(substr($match[2], -1) == '!') {
                if(!$this->statsavail) $this->Log('Stats Upgrades Available', 'I');
                $this->statsavail = true;
            } else $this->statsavail = false;
        }       

        //health
        if(preg_match('/<span id="healthCurrent"(.*?)>(.*?)<\/span>(.*?)<span id="healthMax">(.*?)<\/span>/', $body, $match)) {
            if($match[2] != $this->health || $match[4] != $this->maxhealth) {
                $this->Log(sprintf('Health: %d/%d', $match[2], $match[4]), 'I');
            }
            $this->health = (int)$match[2];
            $this->maxhealth = (int)$match[4];
        }       

        //energy
        if(preg_match('/<span id="energyCurrent"(.*?)>(.*?)<\/span>(.*?)<span id="energyMax">(.*?)<\/span>/', $body, $match)) {
            if($match[2] != $this->energy || $match[4] != $this->maxenergy) {
                $this->Log(sprintf('Energy: %d/%d', $match[2], $match[4]), 'I');
            }
            $this->energy = (int)$match[2];
            $this->maxenergy = (int)$match[4];
        }       

        //stamina
        if(preg_match('/<span id="staminaCurrent"(.*?)>(.*?)<\/span>(.*?)<span id="staminaMax">(.*?)<\/span>/', $body, $match)) {
            if($match[2] != $this->stamina || $match[4] != $this->maxstamina) {
                $this->Log(sprintf('Stamina: %d/%d', $match[2], $match[4]), 'I');
            }
            $this->stamina = (int)$match[2];
            $this->maxstamina = (int)$match[4];
        }

        //favor points
        if(preg_match('#Godfather</a(.*?)"amount">(.*?)</span#ms', $body, $match)) {
            if($match[2] != $this->favorpoints) {
                $this->Log(sprintf('FavorPoints: %d', $match[2]), 'I');
            }
            $this->favorpoints = $match[2];
        }       

        //timers, fuck off some of this regex in place for json rape :)
        if(preg_match('#setTopBarTimerData\((.*?)\);#', $body, $match)) {
            //var_dump(json_decode($match[1]));
            //exit;
            $o = json_decode($match[1]);
            $this->timeleft = $o->cash->timeLeft;
            $this->energyrate = $o->energy->rate;
        }

        return;
    }

    function UpdateMenuLinks($resp) {
        if(!$resp) return false;
        foreach($this->menulinks as $k => $v) {
            if(strstr($resp['url'], $k) !== false) continue;
            if(preg_match(sprintf('/href="\/%s\.php(.*?)"/', $k), $resp['body'], $match)) {
                $this->menulinks[$k] = sprintf('%s.php%s', $k, $match[1]);
                $this->Log(sprintf('Updating Link for %s', $k), 'D');
            }
        }
    }

    function Go($page = false) {
        $p = false;
        switch(strtolower($page)) {
            case false:
            case 'home':
                $func = 'HomePage';
                break;
            case 'missions':
                $func = 'Missions';
                break;
            case 'attack':
                $func = 'Attack';
                break;
            case 'hitlist':
                $func = 'HitList';
                break;
            case 'investment':
                $func = 'Investment';
                break;
            case 'recruit':
                $func = 'Recruit';
                break;
            case 'favor':
                $func = 'Favor';
                break;
            case 'equiptment':
                $func = 'Equiptment';
                break;
            case 'bank':
                $func = 'Bank';
                break;
            case 'profile':
                $func = 'Profile';
                break;
            case 'hospital':
                $func = 'Hospital';
                break;
            default:
                $func = 'request';
                $p = $page;                
                break;
        }

        //wait time, i am a human lul
        usleep(rand(250000,400000));

        if($r = $this->$func($p)) {
            $this->UpdateStats($r['body']);
            $this->UpdateMenuLinks($r);
        }
        return $r;
    }

    //change page functions.    
    
    #FIXME: 
    /* consolidate this shit, always end up adding these when im coding on
     * autopilot thinking about how i should clean it up
     */

    function HomePage() {
        $req = $this->request('home.php?showHomeBg=true');
        return ($this->OK($req)) ? $req : false;
    }

    function Missions() {
        while(!$this->menulinks['missions']) $this->Go('home');
        $req = $this->request($this->menulinks['missions']);
        return ($this->OK($req)) ? $req : false;
    }

    function Attack() {
        while(!$this->menulinks['fight']) $this->Go('home');
        $req = $this->request($this->menulinks['fight']);
        return ($this->OK($req)) ? $req : false;
    }

    function HitList() {
        while(!$this->menulinks['hitlist']) $this->Go('attack');
        $req = $this->request($this->menulinks['hitlist']);
        return ($this->OK($req)) ? $req : false;
    }

    function Investment() {
        while(!$this->menulinks['investment']) $this->Go('home');
        $req = $this->request($this->menulinks['investment']);
        return ($this->OK($req)) ? $req : false;
    }

    function Recruit() {
        while(!$this->menulinks['group']) $this->Go('home');
        $req = $this->request($this->menulinks['group']);
        return ($this->OK($req)) ? $req : false;   
    }

    function Favor() {
        while(!$this->menulinks['favor']) $this->Go('home');
        $req = $this->request($this->menulinks['favor']);
        return ($this->OK($req)) ? $req : false;
    }

    function Bank() {
        while(!$this->menulinks['bank']) $this->Go('home');
        $req = $this->request($this->menulinks['bank']);
        return ($this->OK($req)) ? $req : false;
    }

    function Equiptment() {
        while(!$this->menulinks['equiptment']) $this->Go('home');
        $req = $this->request($this->menulinks['equiptment']);
        return ($this->OK($req)) ? $req : false;
    }

    function Profile() {
        while(!$this->menulinks['profile']) $this->Go('home');
        $req = $this->request($this->menulinks['profile']);
        return ($this->OK($req)) ? $req : false;
    }

    function Hospital() {
        while(!$this->menulinks['hospital']) $this->Go('home');
        $req = $this->request($this->menulinks['hospital']);
        return ($this->OK($req)) ? $req : false;
    }

    function LatestComments() {
        //make sure requests come from the home page?
        if(!strstr($this->referer, 'home')) $this->HomePage();

        $req = $this->request('ajax/getNewsFeedStories.php?selectedTab=comment');
        //var_dump($req);
        #FIXME: probably wanna preg_match_all and return array of comments
        return ($this->OK($req)) ? $req : false;
    }

    function Comments() {
        $req = $this->Go('profile');
        
        if(preg_match('/<a href="\/(.*?)">Comments<\/a></', $req['body'], $match)) {
            $comments = array();
            $i = 0;
            while($req = $this->Go($match[1])) {
                if(preg_match_all('/<td class="newsFeedItemMsg">(.*?)<\/td>/ms', $req['body'], $matches) > 0) {
                    foreach($matches[1] as $k => $v) {
                        if(preg_match('/<a href="\/(.*?)">(.*?)<\/a> wrote/ms', $v, $m)) {
                            $comments[$i]['Profile'] = $m[1];
                            $comments[$i]['Author'] = $m[2];
                            if(preg_match('#puid=(.*?)&#ms', $m[1], $ma)) {
                                $comments[$i]['ProfileID'] = $ma[1];
                            }
                        }
                        if(preg_match('/You wrote/ms', $v, $m)) {
                            $comments[$i]['Author'] = $this->name;
                            $comments[$i]['Profile'] = 'profile';
                        } 
                        if(preg_match('/<div style="font-weight: bold; width: 250px">(.*?)<\/div>/ms', $v, $m)) {
                            $comments[$i]['Comment'] = $m[1];
                        }
                        if(preg_match('/<div style="color: #AFAFAF">(.*?) ago<\/div>/', $v, $m)) {
                            $comments[$i]['Time'] = $m[1];
                        }
                        $i++;
                    }
                }
                if(preg_match('/<a href="\/(.*?)">Next 20</', $req['body'], $match) === 0) break;
            }
        } else return false;

        return (count($comments) > 0) ? $comments : false;
    }

    function UserInfo($profileurl = false) {
        if(!$profileurl) return false;
        $req = $this->Go($profileurl);
        //var_dump($req);
        $user = array();
        //probably add get items etc so you can calculate fight or hitlist
        if(preg_match('/href=\'\/fight\.php\?(.*?)\'/ms', $req['body'], $mat)) {
            $user['AttackURL'] = sprintf("fight.php?%s", $mat[1]);
        }
        if(preg_match('/href=\'\/bounty\.php\?(.*?)\'/ms', $req['body'], $mat)) {
            $user['HitlistURL'] = sprintf("bounty.php?%s", $mat[1]);
        }
        if(preg_match('/"profileHeader"(.*?)bold">(.*?)<(.*?)Level (.*?) (.*?) </ms', $req['body'], $m)) {
            $user['Name'] = $m[2];
            $user['Level'] = $m[4];
            $user['Class'] = $m[5];
            if(preg_match('/puid=(.*?)&/', $profileurl, $ma)) {
                $user['ProfileID'] = $ma[1];
                return $user;
            }
        }

        return false;
    }

    function Fights() {
        //make sure requests come from the home page?
        if(!strstr($this->referer, 'home')) $this->HomePage();

        $req = $this->request('ajax/getNewsFeedStories.php?selectedTab=fight');
        if($this->OK($req)) {
            if(preg_match_all('/<td class="newsFeedItemMsg">You were attacked by (.*?)!(.*?)<span(.*?)>(.*?)<\/span>(.*?)<span class="attackTime">(.*?)<\/span>(.*?)\/profile\.php\?(.*?)\'(.*?)<\/td>/ism', $req['body'], $match) > 0) {
 
                $this->Log(sprintf("Last %d battles", count($match[1])), 'I');

                $result = array();
                foreach($match[1] as $k => $v) {
                    $result[$k] = array(
                        'Name' => $v,
                        'Result' => $match[4][$k],
                        'Outcome' => $this->parseFightOutcome($match[4][$k], $match[5][$k]),
                        'Time' => $match[6][$k],
                        'Profile' => sprintf("profile.php?%s", $match[8][$k]),
                    );
                    if(preg_match('/puid=(.*?)&/ms', $result[$k]['Profile'], $pmatch)) {
                        $result[$k]['ProfileID'] = $pmatch[1];
                    }
                    $this->Log(sprintf('%s against %s. %s (%s)', $result[$k]['Result'], $result[$k]['Name'], $result[$k]['Outcome'], $result[$k]['Time']), 'I');
                }
                return $result;
            }
        } 
        return false;
    }

    function RealEstate() {
        $req = $this->Go('investment');

        if(preg_match_all('/<table class="reTable">(.*?)<\/table>/sm', $req['body'], $matches) > 0) {
            $items = array();
            foreach($matches[1] as $k => $item) {
                if(preg_match('/href="\/investment.php\?action=buy(.*?)"/', $item, $match)) {
                    $items[$k]['Buy'] = sprintf("investment.php?action=buy%s", $match[1]);
                } else continue; //fuck it if its locked.

                if(preg_match('/<div class="reName"(.*?)>(.*?)<\/div>/', $item, $match)) {
                    $items[$k]['Name'] = $match[2];
                }
                if(preg_match('/Income: <span(.*?)<span(.*?)>(.*?)<\/span>/', $item, $match)) {
                    $items[$k]['Income'] = $this->launderMoney($match[3]);
                }
                if(preg_match('/reBuyAction(.*?)class="cash(.*?)<span(.*?)>(.*?)</ms', $item, $match)) {
                    $items[$k]['Cost'] = $this->launderMoney($match[4]);
                }
                if(preg_match('/class="ownedNum(.*?)>(.*?)</', $item, $match)) {
                    $items[$k]['Owned'] = $match[2];
                }
                $this->Log(sprintf("RealEstate: %s %s (%s p/h for %s)", $items[$k]['Owned'], $items[$k]['Name'], $items[$k]['Income'], $items[$k]['Cost']), 'D');
            }
        }
        return $items;
    }

   /*
    *  Calculate best real estate deals
    *  false false = only calculate
    *  false true = only buy the best rate or nothing
    *  true true = buy the best you can afford
    */ 

    function TopRealEstate($afford = false, $buy = false, $budget = false) {
        $items = $this->RealEstate();
        
        if($budget === false) {
             $budget = $this->cash;
        } else {
            if($budget > $this->cash) $buy = false;
        }

        if($afford) { 
            $this->Log('Calculating best you can afford', 'I');
        } 
        $top = false;
        foreach($items as $k => $v) {
            $items[$k]['NewCost'] = $this->launderMoney($v['Cost']);

            $items[$k]['NewIncome'] = $this->launderMoney($v['Income']);

            $price = $items[$k]['NewCost'] / $items[$k]['NewIncome'];
            $items[$k]['Price'] = $price;
            $this->Log(sprintf('TopRealEstate %s @ %s', $items[$k]['Name'], $price), 'D');
            if($top === false || $price < $top) { 
                if($afford && $budget < $items[$k]['NewCost']) continue;
                $topk = $k;
                $top = $price;
            }
        }
        if(!$top) {
            $this->Log('You cannot afford Real Estate', 'I');
            return false;
        }
        
        $this->Log(sprintf('Top %s Real Estate is: %s at $%s per dollar of income per hour', ($afford) ? 'affordable' : '', $items[$topk]['Name'], $top), 'I');

        $items[$topk]['Price'] = $top;

        if($buy && $this->cash >= $items[$topk]['NewCost']) { 
            $this->Log('Buying', 'N');
            $req = $this->request($items[$topk]['Buy']);
        } elseif($buy) {
            $this->Log(sprintf('You cannot afford %s (%s)', $items[$topk]['Name'], $items[$topk]['Cost']), 'I');
            return false;
        }
        return $items[$topk];
    }

    function Invites() {
        $req = $this->Go('recruit');
        if(preg_match_all('/<tr>(\s*?)<td class="mobInviter">(.*?)<\/tr>/ism', $req['body'], $matches) > 0) {
            $result = array();
            foreach($matches[2] as $k => $v) {
                if(preg_match('/<div class="mobInviterInner"><a href="\/(.*?)">(.*?)<\/a>/ms', $v, $match)) {
                    $result[$k]['Profile'] = $match[1];
                    $result[$k]['Name'] = $match[2];
                }

                if(preg_match('/<a href="\/group.php\?accept=(.*?)"/', $v, $match)) {
                    $result[$k]['Accept'] = sprintf("group.php?accept=%s", $match[1]);
                }

                if(preg_match('/<a href="\/group.php\?reject=(.*?)"/', $v, $match)) {
                    $result[$k]['Reject'] = sprintf("group.php?reject=%s", $match[1]);
                }
                $this->Log(sprintf('Mob request from %s', $result[$k]['Name']), 'I');
            }
            $this->Log(sprintf('Mob request total: %d', count($result)), 'I');
            return $result;
        } else return false; //no matches
    }

    function RespondToInvites($method = true) {
        switch($method) {
            case false:
                $action = 'Reject';
                break;
            default:
            case true:
                $action = 'Accept';
                break;
        }
        while($invites = $this->Invites()) {
            $this->Log(sprintf('%sing mob request from  %s', $action, $invites[0]['Name']), 'I');
            $this->Go($invites[0][$action]);
        }
        return true;
    }

    //bankstuff
    function BankBalance() {
        $req = $this->Go('bank');

        if(preg_match('/Bank Balance:(.*?)class="cash"><span(.*?)>(.*?)<\/span/sm', $req['body'], $match)) {
            if($this->bankbalance != $this->launderMoney($match[3])) {
                $this->Log(sprintf('Your Bank Balance is $%s', $this->launderMoney($match[3])), 'I');
                $this->bankbalance = $this->launderMoney($match[3]);
            }
            return $this->bankbalance;
        } else return false;
    }

    function BankDeposit($amount = false) {
        if($amount < 10 && $amount !== false) return false;
        return $this->BankAction(true, $amount);
    }

    function BankWithdraw($amount = false) {
        if($amount == 0 && $amount !== false) return false;
        return $this->BankAction(false, $amount);
    }

    function BankAction($method = true, $amount = false) {
        $total = 0;
        $bb = true;
//        while($amount !== $total && $bb !== 0) {
            switch($method) {
                default:
                case true:
                    $action = 'Deposit';
                    $field = 'deposit';
                    if($amount == false) { 
                        $amount2 = $this->cash;
                    } else {
                        $amount2 = $amount;
                    }
                    $fee = round(($amount2/100)*10);
                    $this->Log(sprintf('Depositing: $%d Fee: $%d', $amount2-$fee, $fee), 'I');
                    break;
                case false;
                    $action = 'Withdraw';
                    $field = 'withdraw';    
                    if($amount == false) {
                       $bb = $this->BankBalance();
                        if($bb > 1000000000 || $bb < -1) { //fuqin int rapz
                            $amount2 = 1000000000;
                        } else $amount2 = $bb;
                    } else {
                        $amount2 = $amount;
                    }
                    $this->Log(sprintf('Withdrawing: $%d', $amount2), 'I');
                    break;
            }

            $this->Go('bank');

            $post = array(sprintf('%sAmount', $field) => (double)$amount2,
                          'action' => $action,
                          'sk' => 1);

            usleep(rand(50000,100000));
            $req = $this->request('bank.php', 'POST', $post);
            if($this->OK($req)) {
                $total += $amount2;
                $bb = $this->BankBalance();
            }
//        } 
        $this->Auth(); //bandaid fix.
        return $bb;
    }

    function Heal() {
        $req = $this->Go('hospital');

        if(preg_match('#You currently have (.*?)\$(.*?)<#ms', $req['body'], $m)) {
            $bb = $this->launderMoney($m[2]);
        } else return false;

        if(preg_match('#Heal for <span(.*?)>(.*?)<#ms', $req['body'], $m)) {
            $cost = $this->launderMoney($m[2]);
        } else return false;

        if(preg_match('#href="/hospital\.php\?action=heal(.*?)"#ms', $req['body'], $m)) {
            $action = sprintf("hospital.php?action=heal%s", $m[1]);
        } else return false;

        if($bb > $cost) {
            $this->Go($action);
            //update $this->bb
            $this->BankBalance();
            return true;
        }
        return false;
    }

    //missions......
    function GetMissions($loc = false) {
        $req = $this->Go('missions');
        if(strpos($req['url'], 'missions.php?cat=1') === false) $req = $this->Go('missions.php?cat=1');
        $p = false;
        $missions = array();
        $mk=0;
        do {
            if($p !== false) $req = $this->Go(sprintf('missions.php?cat=%s', $p[1]));
            if(preg_match_all('#<a href="/missions\.php\?cat=(.*?)">(.*?)<(.*?)>(.*?)<#ms', $req['body'], $m) > 0) {
                foreach($m[1] as $k => $v) {
                    $location = $m[2][$k];
                        if($loc) if($loc != $location) continue; 

                    $noreq = false;

                    $url = sprintf('missions.php?cat=%s', $v);
                    if(substr($req['url'], -strlen($url)) == $url) {
                        $req2 = $req;
                        $noreq = true;
                    }
                    if($noreq == false) $req2 = $this->Go(sprintf('missions.php?cat=%s', $v));

                    if(preg_match_all('/<table class="missionTable"(.*?)<\/table>/ms', $req2['body'], $ma) > 0) {
                        foreach($ma[1] as $l => $w) {
                            //url
                            if(preg_match('#href="\/(.*?)"(.*?)Do It#ms', $w, $mat)) {
                                $missions[$mk]['URL'] = $mat[1];
                            } else continue; //fuck it if its locked

                            $missions[$mk]['Location'] = $location;

                            if(preg_match('#"missionNam(.*?)">(.*?)<#ms', $w, $mat)) {
                                $missions[$mk]['Name'] = $mat[2];
                            } 
    
                            if(preg_match('#"requiredEnergy">(.*?)<#ms', $w, $mat)) {
                                $missions[$mk]['Energy'] = (int)$mat[1];
                            } 

                            if(preg_match('#"cash">(.*?)>(.*?)<(.*?)>(.*?)<(.*?)>(.*?)<#ms', $w, $mat)) {
                                $missions[$mk]['CashMin'] = $this->launderMoney($mat[2]);
                                $missions[$mk]['CashMax'] = $this->launderMoney($mat[6]);
                            } 

                            if(preg_match('#"masteryBarProgress"(.*?)>(.*?)%(.*?)>Rank (.*?)<#ms', $w, $mat)) {
                                $missions[$mk]['Progress'] = (int)$mat[2];
                                $missions[$mk]['Rank'] = (int)$mat[4];
                            } 

                            if(preg_match('#\+(.*?) Experience#ms', $w, $mat)) {
                                $missions[$mk]['Experience'] = (int)$mat[1];
                            } 

                            if(preg_match('#"requiredGroup">(.*?)<#ms', $w, $mat)) {
                                $missions[$mk]['MobSize'] = (int)$mat[1];
                            } else $missions[$mk]['MobSize'] = 0; 

                            $missions[$mk]['ExperienceRate'] = $missions[$mk]['Experience'] / $missions[$mk]['Energy'];

                            $missions[$mk]['CashAverage'] = ($missions[$mk]['CashMin'] + $missions[$mk]['CashMax']) / 2;
                            $missions[$mk]['CashRate'] = $missions[$mk]['CashAverage'] / $missions[$mk]['Energy'];
 
                            $missions[$mk]['Loot'] = (strpos($w, 'Chance of Loot') !== false) ? true : false; 

                            $missions[$mk]['Required'] = array();
                            if(preg_match_all('#"equipmentRequiredItem(.*?)"equipmentReqItemPic"(.*?)</div>#ms', $w, $matc) > 0) {
                                //$matc[2] filter small image later for www
                                foreach($matc[1] as $j => $x) {
                                    if(preg_match('#itemName="(.*?)"#ms', $x, $match)) {
                                        $missions[$mk]['Required'][$j]['Name'] = $match[1];
                                    }

                                    if(preg_match('#itemOffence="(.*?)"#ms', $x, $match)) {
                                        $missions[$mk]['Required'][$j]['Offence'] = $match[1];
                                    }

                                    if(preg_match('#itemDefence="(.*?)"#ms', $x, $match)) {
                                        $missions[$mk]['Required'][$j]['Defence'] = $match[1];
                                    }

                                    if(preg_match('#itemPrice="(.*?)"#ms', $x, $match)) {
                                        $missions[$mk]['Required'][$j]['Cost'] = $this->launderMoney($match[1]);
                                    }

                                    if(preg_match('#itemOwned="(.*?)"#ms', $x, $match)) {
                                        $missions[$mk]['Required'][$j]['Owned'] = (int)$match[1];
                                    }

    /*                              //appears unused
                                    if(preg_match('#itemType="(.*?)"#ms', $x, $match)) {
                                        $missions[$mk]['Required'][$j]['Type'] = $match[1];
                                    }

                                    if(preg_match('#itemDesc="(.*?)"#ms', $x, $match)) {
                                        $missions[$mk]['Required'][$j]['Desc'] = $match[1];
                                    }
    */
                                    if(preg_match('#canBuyItem="(.*?)"#ms', $x, $match)) {
                                        //var_dump($match[1]);
                                        $missions[$mk]['Required'][$j]['CanBuy'] = ($match[1] == 1) ? true : false;
                                    }
    
                                    if(preg_match('#itemNeeded="(.*?)"#ms', $x, $match)) {
                                        $missions[$mk]['Required'][$j]['Needed'] = $match[1];
                                    } else $missions[$mk]['Required'][$j]['Needed'] = false;


                                    if(preg_match('#itemTotal="(.*?)"#ms', $x, $match)) {
                                        $missions[$mk]['Required'][$j]['TotalCost'] = $this->launderMoney($match[1]);
                                    } else $missions[$mk]['Required'][$j]['TotalCost'] = false;

                                    if(preg_match('#itemBuyLink="/(.*?)"#ms', $x, $match)) {
                                        $missions[$mk]['Required'][$j]['BuyURL'] = $match[1];
                                    } else $missions[$mk]['Required'][$j]['BuyURL'] = false;
                                    

                                }
                            } else $missions[$mk]['Required'] = false;

                            //increment mission counter
                            $mk++;
                        }
                    }
                }
            }
        } while(preg_match('#href="/missions\.php\?cat=(.*?)">(.*?)<img(.*?)tabforward.png(.*?)">#ms', $req['body'], $p) == 1);

        return $missions;
    }

    function MissionBestExp($incomplete_only = false, $no_needed = false, $missions_or_location = false) {
        $missions = (!is_array($missions_or_location)) ? $this->GetMissions($missions_or_location) : $missions_or_location;
        $best = false;
        foreach($missions as $k => $v) {
            $b = ($best === false) ? array('ExperienceRate' => 0) : $best;
            if($v['ExperienceRate'] >= $b['ExperienceRate'] && $v['Energy'] <= $this->energy) {
                if($incomplete_only) {
                    if($v['Progress'] == 100) continue;
                } 
                if($v['MobSize'] > $this->mobsize) continue;
                $continue = false;
                $totalcost = 0;
//                if($v['Name'] == 'Expand Your Influence') { var_dump($v); exit; }
                if($v['Required'] !== false) foreach($v['Required'] as $l => $w) {
                    if($no_needed && $w['Needed'] > 0) $continue = true;
                    if($w['Owned'] == 0 && $w['BuyURL'] == false) $continue = true;
                    if($w['Needed'] > 0) $totalcost += $w['TotalCost'];
                }
                if($continue === true) continue;
                if($totalcost > $this->cash + $this->bankbalance) continue;
                $this->Log(sprintf("MissionBestExp: %s ExpRate: %s CashRate: %s", $v['Name'], $v['ExperienceRate'], $v['CashRate']), 'D');
                if(!$incomplete_only && $best !== false) {
                    if($best['ExperienceRate'] == $v['ExperienceRate'] && $best['CashRate'] < $v['CashRate']) {
                        $best = $v; 
                        continue;
                    }
                }
                $best = $v;
            }
        }
        if($best !== false) {
            $this->Log(sprintf("MissionBestExp: %s ExpRate: %s CashRate: %s", $best['Name'], $best['ExperienceRate'], $best['CashRate']), 'I');
        } else {
            $this->Log('MissionsBestExp: no mission', 'I');
            return false;
        }
        return $best;
    }

    function DoMission($mission) {
        if(!$mission) return false;
        if($mission['Energy'] > $this->energy) {
            //probably log stuff here
            return false;
        }
/*        preg_match('/cat=(.*?)&/', $mission['URL'], $match);
        if(strpos($this->referer, sprintf('cat=%d', $match[1])) === false) {
            $this->GetMissions($mission['Location']);
        } */
        if($mission['Required'] !== false) {
            foreach($mission['Required'] as $k => $v) {
                if($v['Needed'] > 0) {
                    if($v['TotalCost'] < $this->cash && $v['BuyURL'] != false) {
                        $this->Log(sprintf("Buying %s %s's for %s", $v['Needed'], $v['Name'], $v['TotalCost']), 'N');
                        $this->Go($v['BuyURL']);
                    } elseif($v['TotalCost'] < ($this->cash + $this->bankbalance) && $v['BuyURL'] != false) {
                        $needed = $v['TotalCost'] - $this->cash;
                        if($needed > 0 && $this->bankbalance > $needed) {
                            $this->BankWithdraw($needed);
                            $this->Log(sprintf("Buying %s %s's for %s", $v['Needed'], $v['Name'], $v['TotalCost']), 'N');
                            $this->Go($v['BuyURL']);
                        }
                    } else {
                        $this-Log('Unable to Buy %s', $v['Name'], 'N');
                        return false;
                    }
                }
            }
        }
        $req = $this->Go($mission['URL']);
        //var_dump($req);
        #FIXME: add stuff to parse outcome...
    }

    //attack and hitlist
    function Targets() {
        $req = $this->Go('attack');
        $targets = array();
        if(preg_match_all('#"fightTable"(.*?)</table>#ms', $req['body'], $m) > 0) {
            foreach($m[1] as $k => $v) {
                if(preg_match('#"fightMobster">(.*?)href="/(.*?)">(.*?)</a></div><div>Level (.*?) (.*?) <#ms', $v, $ma)) {
                    $targets[$k]['Name'] = $ma[3];
                    $targets[$k]['Level'] = $ma[4];
                    $targets[$k]['Class'] = $ma[5];
                    $targets[$k]['Profile'] = $ma[2];
                    if(preg_match('#puid=(.*?)&#', $ma[2], $mat)) {
                        $targets[$k]['ProfileID'] = $mat[1];
                    } else {
			unset($targets[$k]);
			continue;
		    }
                }
                if(preg_match('#fightMobSize">(.*?)</td>#ms', $v, $ma)) {
                    $targets[$k]['MobSize'] = (int)trim($ma[1]);
                }
                if(preg_match('#href="/fight\.php\?(.*?)"(.*?)Attack#ms', $v, $ma)) {
                    $targets[$k]['AttackURL'] = sprintf("fight.php?%s", $ma[1]);
                }
            }
            return $targets;
        }
        return false;
    }

    function Fight($target) {
        if(!is_array($target) || $this->health < 25 || $this->stamina == 0) return false;
        $this->Log(sprintf('Fighting! %s Level: %s MobSize: %s', $target['Name'], $target['Level'], $target['MobSize']), 'N');
        $req = $this->Go($target['AttackURL']);
        //need some parsing here
        return $req;
    }

    function DoHitlist($target) {
        if(!array_key_exists('ProfileID', $target) || $target['ProfileID'] == 0) return false;
        $dummies = $this->Targets();
        $d = $dummies[rand(0,9)];
        $profileurl = str_replace($d['ProfileID'], $target['ProfileID'], $d['Profile']);
        //$ui = $this->UserInfo($profileurl);
        //var_dump($target, $profileurl, $ui);
    }
               
    //utilities
    function parseFightOutcome($result, $outcome) {
        if(!$outcome) return false;
        if($result == 'WON') {
            if(preg_match('/! (.*?)<br\/>/', $outcome, $match)) {
                return $match[1];
            }
        } else {
            if(preg_match('/<span(.*?)>(.*?)<\/span> and taking (.*?)<br\/>/', $outcome, $match)) {
                return sprintf('You lost %s and %s', $match[2], $match[3]);
            }
        }
        return false;
    }

    function launderMoney($dirtymoney) {
        $f = '(\$|,)';
        $r = '';
        $cash = preg_replace($f, $r, $dirtymoney);

        if(substr($cash, -4) == ' bil') {
            $cash = substr($cash, 0, -4) * 1000000000;
        }
        if(substr($cash, -4) == ' mil') {
            $cash = substr($cash, 0, -4) * 1000000;
        }
        if(substr($cash, -1) == 'K') {
            $cash = substr($cash, 0, -1) * 1000;
        }
        return $cash;
    }

}

