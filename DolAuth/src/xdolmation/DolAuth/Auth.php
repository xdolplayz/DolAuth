<?php
namespace xdolmation\DolAuth;

use pocketmine\{Player, Server};
use pocketmine\command\{Command, CommandSender, ConsoleCommandSender};
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerQuitEvent;

use function PHPSTORM_META\type;

//TO-DO:
//Add discord logging for registration and new device logins, also add logging for admin commands and attempted account cracking
//Add the ability for the players to see device OSes used
//Add last device id/device os logged in on and allow players to see this
//Connect the IP, device ID and device OS 
//Add an option for players to remove devices from their authed accounts

class Auth extends PluginBase implements Listener{

    private $logindata;
    public $authed;
    public $data;
    public $config;
    private $newdata;

    public function onEnable(){
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->data = new Config($this->getDataFolder() . "data.json", Config::JSON);

    }

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool{
        $arguments = "§l(§6AUTHENTICATE§r§l)§r §c/auth (register/login) password";
        $username = strtolower($sender->getName());
        $adminusage = "§l(§6AUTHENTICATE§r§l)§r ADMIN USAGE: §a/auth admin (seepassword/resetdata/setpassword/seeinfo) (username) (password to set if using setpassword)";
        switch($cmd->getName()){
            case "auth":
                if(!isset($args[0])){$sender->sendMessage($arguments); return true;}
                if(!isset($args[1])){$sender->sendMessage($arguments); return true;}
                switch($args[0]){
                    case "admin":
                        $username = strtolower($args[2]);
                        if(!$sender->isOp() && !$sender->hasPermission("auth.staff")){return true;}
                        switch($args[1]){
                            case "seepassword":
                                $userdata = $this->data->get($username);
                                if(is_bool($userdata)){$sender->sendMessage("§l(§6AUTHENTICATE§r§l)§r§c That user's data is not set."); return true;}
                                if(!isset($args[2])){$sender->sendMessage($adminusage); return true;}
                                $sender->sendMessage("§l(§6AUTHENTICATE§r§l)§r§a " . $username . "'s password is §c" . $userdata['password']);
                                return true;
                            break;

                            case "resetdata":
                                $userdata = $this->data->get($username);
                                if(is_bool($userdata)){$sender->sendMessage("§l(§6AUTHENTICATE§r§l)§r§c That user's data is not set."); return true;}
                                if(!isset($args[2])){$sender->sendMessage($adminusage); return true;}
                                $this->data->__unset($username);
                                $sender->sendMessage("§l(§6AUTHENTICATE§r§l)§r§a Deleted §c" . $username . "§a's data.");
                                return true;
                            break;

                            case "setpassword":
                                $userdata = $this->data->get($username);
                                if(is_bool($userdata)){$sender->sendMessage("§l(§6AUTHENTICATE§r§l)§r§c That user's data is not set."); return true;}
                                if(!isset($args[2])){$sender->sendMessage($adminusage); return true;}
                                if(!isset($args[3])){$sender->sendMessage($adminusage); return true;}
                                $userdata['password'] = $args[3];
                                $this->data->set($username, $userdata);
                                $sender->sendMessage("§l(§6AUTHENTICATE§r§l)§r§a Set §c" . $username . "§a's password to §c" . $args[3] . "§a.");
                                return true;
                            break;

                            case "seeinfo":
                        if(!$sender->isOp() && !$sender->hasPermission("auth.admin")){return true;}
                                $userdata = $this->data->get($username);
                                if(is_bool($userdata)){$sender->sendMessage("§l(§6AUTHENTICATE§r§l)§r§c That user's data is not set."); return true;}
                                if(!isset($args[2])){$sender->sendMessage($adminusage); return true;}
                                $dataString = "§6IPs: " . implode(", ", $userdata['ip']) . "\n§3Device IDs: " . implode(", ", $userdata['deviceid']) . "\n§9OS: " . implode(", ", $userdata['device']) . "\n§dSelf Signed ID: " . $userdata['selfsignedid'] . "\n§bXUID: " . $userdata['xuid'] . "\n§dPassword: " . $userdata['password'];
                                $sender->sendMessage("§l(§6AUTHENTICATE§r§l)§r§a §c" . $username . "§a's data: \n" . $dataString);
                                return true;
                            break;
                            
                        }


                    break;

                    case "register":
                        if(!$sender instanceof Player){$sender->sendMessage("You can only run this in game."); return true;}
                        $userdata = $this->data->get($username);
                        if(!is_bool($userdata)){$sender->sendMessage("§l(§6AUTHENTICATE§r§l)§r§c You have already registered.  If you would like to reset your password open a ticket at §9discord.gg/ownage"); return true;}
                        if(!isset($args[1])){$sender->sendMessage("§l(§6AUTHENTICATE§r§l)§r §cPlease enter a password to set.\nIf you forget this password you can get it again by opening a ticket at discord.gg/ownage"); return true;}
                        $this->newdata[$username]['password'] = $args[1];
                        $this->data->set($username, $this->newdata[$username]);
                        unset($this->newdata[$username]);
                        $this->authed[$username] = $username;
                        $sender->sendMessage("§l(§6AUTHENTICATE§r§l)§r §aYou have registered this account and set it's password.\nYou will only need to use this if you login from a new device.  Once you enter your password on that account you will not have to do it again.\nIf you forget your password, open a ticket at discord.gg/ownage");
                    break;

                    case "login":
                        if(!$sender instanceof Player){$sender->sendMessage("You can only run this in game."); return true;}
                        $userdata = $this->data->get($username);
                        if(is_bool($userdata)){$sender->sendMessage("§l(§6AUTHENTICATE§r§l)§r§c You need to register your account.  If this is a mistake, open a ticket at §9discord.gg/ownage"); return true;}
                        if(!isset($args[1])){$sender->sendMessage("§l(§6AUTHENTICATE§r§l)§r §cPlease enter a password to set.\nIf you forgot your password you can get it again by opening a ticket at discord.gg/ownage"); return true;}
                        $enteredpass = $args[1];
                        $userdata = $this->data->get($username);
                        $actualpass = $userdata['password'];
                        if($enteredpass == $actualpass){
                            $this->authed[$username] = $username;
                            $sender->sendMessage("§l(§6AUTHENTICATE§r§l)§r §aThis device has been registered and will now be recognized and authenticated automatically when logging in.");
                            if(!in_array($this->logindata[$username]['ip'], $userdata['ip'])){$userdata['ip'][] = $this->logindata[$username]['ip'];}
                            $userdata['deviceid'][] = $this->logindata[$username]['deviceid'];
                            $userdata['device'][] = $this->logindata[$username]['device'];
                            $this->data->set($username, $userdata);
                            return true;
                        }else{
                            $sender->sendMessage("§l(§6AUTHENTICATE§r§l)§r §cIncorrect password, if you forgot your password open a ticket at §9discord.gg/ownage§c.");
                            return true;
                        }

                    break;
                }




            break;
        }


        
        return true;
    }

    public function onDataPacketRecieve(DataPacketReceiveEvent $ev){
        $packet = $ev->getPacket();
        if(!$packet instanceof LoginPacket){return;}
        $clientData = $packet->clientData;
        $p = $ev->getPlayer();
        $username = strtolower($packet->username);

        $userdata = [
            'ip' => $p->getAddress(),
            'deviceid' => $clientData['DeviceId'],
            'selfsignedid' => $clientData['SelfSignedId'],
            'device' => $this->getDevice($clientData['DeviceOS'])
        ];

        $this->logindata[$username] = $userdata;

        if(!$this->data->__isset($username)){
            $this->newdata[$username] = [
                'ip' => [$this->logindata[$username]['ip']],
                'deviceid' => [$this->logindata[$username]['deviceid']],
                'selfsignedid' => $this->logindata[$username]['selfsignedid'],
                'device' => [$this->logindata[$username]['device']]
            ];
        }
        return;
    }

    public function onPlayerLogin(PlayerLoginEvent $ev){
        $p = $ev->getPlayer();
        $name = strtolower($p->getName());
        $dataArray = $this->data->get($name);
        $this->logindata[$name]['xuid'] = $p->getXuid();
        if(!isset($dataArray) || !isset($dataArray['xuid']) || !isset($this->logindata[$name]['xuid'])){
            $this->newdata[$name]['xuid'] = $p->getXuid();
            return;
        }

    }

    public function onJoin(PlayerJoinEvent $ev){
        $p = $ev->getPlayer();
        $username = strtolower($p->getName());
        $dataArray = $this->data->get($username);


        if(!$this->data->__isset($username)){
            $p->sendMessage("§l(§6AUTHENTICATE§r§l)§r §cPlease register your account by running the command /auth register.");
            return;
        }

        if(in_array($this->logindata[$username]['deviceid'], $dataArray['deviceid'])){
            $this->authed[$username] = $username;
            $p->sendMessage("§aRecognized device, automatically authenticated.");
            return;
        }

        if(!isset($this->authed[$username])){
        $p->sendMessage("§l(§6AUTHENTICATE§r§l)§r §cYou need to authenticate this device.  To do this use /auth login (your password).  If you need help with this please make a ticket at discord.gg/ownage.");
        return;
        }
        
    }

    public function onMove(PlayerMoveEvent $ev){
        $p = $ev->getPlayer();
        $username = strtolower($p->getName());
        if(!isset($this->authed[$username])){
            $ev->setCancelled(true);
            return;
        }
    }

    public function onPlayerCommandPreprocess(PlayerCommandPreprocessEvent $ev){
        $p = $ev->getPlayer();
        $username = strtolower($p->getName());

        if(!$this->data->__isset($username) && !str_starts_with($ev->getMessage(), "/auth")){
            $ev->setCancelled(true);
            $p->sendMessage("§l(§6AUTHENTICATE§r§l)§r §cPlease register your account by running the command /auth register.");
            return;
        }
        if(!isset($this->authed[$username]) && !str_starts_with($ev->getMessage(), "/auth")){
            $ev->setCancelled(true);
            $p->sendMessage("§l(§6AUTHENTICATE§r§l)§r §cYou need to authenticate this device.  To do this use /auth login (your password).  If you need help with this or if you forgot your password, please make a ticket at discord.gg/ownage.");
            return;
        }else{
            return;
        }

    }

    public function getInstance(){
        return self::getInstance();
    }

    public function getDevice(int $id){
        $device = "Unknown";
        switch($id){
            case 1:
                $device = "Android";
            break;

            case 2:
                $device = "IOS";
            break;

            case 3:
                $device = "OSX";
            break;

            case 4:
                $device = "FireOS";
            break;

                        
            case 5:
                $device = "GEARVR";
            break;

                 
            case 6:
                $device = "Hololens";
            break;
                                
            case 7:
                $device = "Windows 10";
            break;
                                    
            case 8:
                $device = "Windows 32";
            break;

            case 9:
                $device = "Unknown";
            break;

            case 10:
                $device = "TV";
            break;

            case 11:
                $device = "Orbis";
            break;

            case 12:
                $device = "Playstation";
            break;

            case -1:
                $device = "Unknown";
            break;
        }
        return $device;
    }

    public function onQuit(PlayerQuitEvent $ev){
        $name = strtolower($ev->getPlayer()->getName());
        if(!isset($this->authed[$name])){return;}
        unset($this->authed[$name]);
        unset($this->logindata[$name]);
    }

    public function onDisable(){
        $this->data->save();
    }

}


?>