<?php
/**
 * 消息分发处理。
 * */
class Dispatcher{

    const USER_POOL_SET = "userpoolset";
    const USER_INFO_PREFIX = "userinfo:hash:";

    private static $_instance = null;
    public static function getInstance() {
        if(self::$_instance == null) {
            self::$_instance = new Dispatcher();
        }
        return self::$_instance;
    }

    public function __construct() {
        $this->redis = new Redis();
        $this->redis->connect("127.0.0.1", 6379);
    }

    public function sendPrivateChat($server, $sendto, $msg){
        $server->push($sendto, $msg);
    }

    public function sendPublicChat($server, $msg) {
        foreach($server->connections as $key=>$fd) {
            $server->push($fd, $msg);
        }
    }

    public function handleAction($server, $from, $data) {
        $action = strval($data['action']);
        $ret = array("type"=>"action", "result"=>"服务异常，待会再试试吧~");
        if($action == "getonlineusers") {
            $userids= $this->redis->smembers(self::USER_POOL_SET);
            $ret['result'] = array();
            $userinfos = array();
            foreach($userids as $userid) {
                array_push($userinfos, $this->redis->hgetall(self::USER_INFO_PREFIX.$userid));
            }
            $ret['action'] = $action;
            $ret['result'] = $userinfos;
            var_dump($ret);
            $this->sendPrivateChat($server, $from, json_encode($ret));
        }else if($action == "registeruser") {
            $ret['type'] = "chat";
            if(!empty($data)) {
                // 拿到最开始的用户填写的信息，并进行解析用于后续使用
                //$nickname = strval($params['nickname']);
                // TODO 后续可能会添加多项用户信息,然后注册到redis的hash用户信息结构中
                $this->redis->sadd(self::USER_POOL_SET, $from);
                $data['userid'] = $from;
                $result = $this->redis->hmset(self::USER_INFO_PREFIX.$from, $data);
                $ret['result'] = "【{$data['nickname']}】加入了聊天室~";
                $this->sendPublicChat($server,json_encode($ret));
            }else{
                $ret['result'] = "您还没有填写自己的用户信息哦~";
                $this->sendPrivateChat($server, $from, json_encode($ret));
            }
        }else if($action == "unregisteruser") {
            $nickname = strval($this->redis->hget(self::USER_INFO_PREFIX.$from, "nickname"));
            $this->redis->srem(self::USER_POOL_SET, $from);
            $this->redis->del(self::USER_INFO_PREFIX.$from);
            $ret['result'] = "【{$nickname}】离开了聊天室";
            $ret['type'] = "chat";
            $this->sendPublicChat($server, json_encode($ret));
        }
    }

    public function handleChat($server, $from, $data) {
        $ret = array("type"=>"chat", "result"=>"服务器异常，稍后再试吧~");
        $chattype = strval($data['chattype']);
        $chatmsg = strval($data['chatmsg']);
        $nickname = strval($this->redis->hget(self::USER_INFO_PREFIX.$from, "nickname"));
        // 暂时先不考虑群组聊天室的实现，简单做下
        if($chattype == "privatechat") {
            // 处理私聊
            $chatto = intval($data['chatto']);
            // TODO 对私聊的两个人可以定制下消息内容，以增强用户体验
            $ret['result'] = "【{$nickname}】对你说：".$chatmsg;
            $server->push($chatto, json_encode($ret));
            $nickname = strval($this->redis->hget(self::USER_INFO_PREFIX.$chatto, "nickname"));
            $ret['result'] = "你对【{$nickname}】说：".$chatmsg;
            $server->push($from, json_encode($ret));
        }else if($chattype == "publicchat") {
            // 处理公聊
            $ret['result'] = "【{$nickname}】对大家说：".$chatmsg;
            foreach($server->connections as $key=>$fd) {
                // TODO 对发消息的人，可以定制下返回内容
                $server->push($fd, json_encode($ret));
            }
        }
    }
}
