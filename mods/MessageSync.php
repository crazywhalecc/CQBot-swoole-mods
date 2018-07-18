<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/7/18
 * Time: 5:54 PM
 */

use DataProvider as DP;
/**
 * Class MessageManager
 * 转发群消息模块
 */
class MessageSync extends ModBase
{
    public function __construct(CQBot $main, $data, bool $mod_cmd = false) {
        parent::__construct($main, $data, $mod_cmd);
        $this->call_task = true;
        if ($this->getMessageType() == "group") {
            if ($this->execute(explode(" ", $data["message"])) !== false) return;
        }
        $ls = Buffer::get("message_transfer");
        if ($this->getMessageType() == "group" && isset($ls[$data["group_id"]])) {
            foreach ($ls[$data["group_id"]] as $v) {
                $msg = $this->msg_replace(Buffer::get("message_transfer_format"), $data);
                $this->sendGroupMsg($v, $msg);
            }
        }
    }

    static function initValues(){
        Buffer::set("message_transfer", DP::getJsonData("transfer.json") ?? []);
        Buffer::set("message_transfer_format", "{group_name} 的消息：\n{user_prefix}({user_id}): {msg}");
        Buffer::set("message_transfer_request", []);
    }

    public function execute($it){
        switch ($it[0]) {
            case "同意同步请求":
                if (!CQUtil::isGroupAdmin($this->data["group_id"], $this->getUserId())) return true;
                $ls = Buffer::get("message_transfer_request");
                if (isset($ls[$this->data["group_id"]])) {
                    foreach ($ls[$this->data["group_id"]] as $k => $v) {
                        $r = $this->setDistribution($k, $v);
                        if ($r === null) {
                            $this->reply("请求失败！炸毛可能不在对方群里！");
                        }
                    }
                    unset($ls[$this->data["group_id"]]);

                }
                else {
                    $this->reply("暂无任何请求！");
                }
                return true;
            case "忽略同步请求":
                if (!CQUtil::isGroupAdmin($this->data["group_id"], $this->getUserId())) return true;
                $ls = Buffer::get("message_transfer_request");
                if (isset($ls[$this->data["group_id"]])) {
                    unset($ls[$this->data["group_id"]]);
                    $this->reply("已忽略同步请求！");
                }
                else {
                    $this->reply("暂无任何请求！");
                }
                return true;
            case "同步消息":
                $msg = "「炸毛群消息转发功能」";
                $msg .= "\n此功能为测试功能";
                $msg .= "\n用处：将一个群的消息转发到另一个群";
                $msg .= "\n也可以将两个群互相转发";
                $msg .= "\n此功能仅可管理员进行设置";
                $msg .= "\n=====指令帮助=====";
                $msg .= "\n同意同步请求：同意其他群=>本群或本群=>其他群的同步请求";
                $msg .= "\n忽略同步请求：同上，忽略";
                $msg .= "\n请求同步消息：向其他群发起同步请求";
                $msg .= "\n取消同步请求：取消一个已经设置同步的群";
                $this->reply($msg);
                return true;
            case "取消同步请求":
                if (!CQUtil::isGroupAdmin($this->data["group_id"], $this->getUserId()) && !$this->main->isAdmin($this->getUserId())) return true;
                if (count($it) == 1) {
                    a1:
                    $msg = "「取消同步请求帮助」";
                    $ls = Buffer::get("message_transfer")[$this->data["group_id"]] ?? [];
                    if ($ls == []) {
                        $msg .= "\n目前没有设置任何同步群";
                    }
                    else {
                        $msg .= "\n已设置本群同步到其他群的列表:";
                        foreach ($ls as $k => $v) {
                            $group_profile = Buffer::get("group_list")[$v];
                            $msg .= "\n" . $group_profile["group_name"] . "(" . $group_profile["group_id"] . ")";
                        }
                        $msg .= "\n如何取消本群向其他群的转发？";
                        $msg .= "\n回复：取消同步请求 目标群号";
                    }

                    $ls = Buffer::get("message_transfer");
                    $ks = 0;
                    foreach ($ls as $k => $v) {
                        if (in_array($this->data["group_id"], $v)) {
                            if($ks == 0) $msg .= "\n已设置其他群向本群转发消息的群列表:";
                            $ks = 1;
                            $group_profile = Buffer::get("group_list")[$k];
                            $msg .= "\n" . $group_profile["group_name"] . "(" . $group_profile["group_id"] . ")";
                        }
                    }
                    if ($ks == 0) {
                        $msg .= "\n暂没有其他群向本群设置转发消息";
                    }
                    else {
                        $msg .= "\n如何取消其他群向本群消息的转发？";
                        $msg .= "\n回复：取消同步请求 目标群号 本群";
                    }
                    $this->reply($msg);
                    return true;
                }
                elseif (count($it) == 2) {
                    $ls = Buffer::get("message_transfer");
                    $ls2 = $ls[$this->data["group_id"]] ?? [];
                    if (!in_array($it[1], $ls2)) {
                        $this->reply("你还没有设置过将消息转发到群" . $it[1] . "呢！");
                        return true;
                    }
                    $inv = array_search($it[1], $ls2);
                    array_splice($ls2, $inv, 1);
                    $ls[$this->data["group_id"]] = $ls2;
                    Buffer::set("message_transfer", $ls);
                    $this->reply("成功取消将本群的消息同步到" . $it[1] . " !");
                    return true;
                }
                else {
                    if ($it[2] != "本群") {
                        goto a1;
                    }
                    $origin = $it[1];
                    $ls = Buffer::get("message_transfer");
                    if (!isset($ls[$origin])) {
                        $this->reply("群" . $origin . "没有设置将消息同步到你的群！");
                        return true;
                    }
                    elseif (!in_array($this->data["group_id"], $ls[$origin])) {
                        $this->reply("群" . $origin . "没有设置将消息同步到你的群！");
                        return true;
                    }
                    else {
                        $this->setDistribution($origin, $this->data["group_id"], false);
                        return true;
                    }
                }
            case "请求同步消息":
                if ($this->getMessageType() != "group") return true;
                if (!CQUtil::isGroupAdmin($this->data["group_id"], $this->getUserId()) && !$this->main->isAdmin($this->getUserId())) {
                    $this->reply("对不起，你不是管理员，不能使用同步消息功能！");
                    return true;
                }
                $mode = "normal";
                if (count($it) < 3) {
                    $msg = "「多群消息同步功能帮助」";
                    $msg .= "\n功能概述：此功能仅可管理员或群主使用";
                    $msg .= "\n用于同步多群的消息，比如A群的消息转发到B群";
                    $msg .= "\n或两个群相互转发";
                    $msg .= "\n用法示例：";
                    $msg .= "\n\"请求同步消息 本群 123456\"";
                    $msg .= "\n作用是，将本群的消息请求同步到123456群";
                    $msg .= "\n如果反着写，就是将123456群的消息同步到本群";
                    $msg .= "\n请求后，对方群的管理员需要同意请求后才可以实现";
                    $this->reply($msg);
                    return true;
                }
                if ($this->data["group_id"] == Framework::$admin_group) $mode = "force";
                $origin = $it[1];
                $target = $it[2];
                if ($mode == "force") {
                    $origin = $origin == "本群" ? $this->data["group_id"] : $origin;
                    $target = $target == "本群" ? $this->data["group_id"] : $target;
                    if ($origin == $target) {
                        $this->reply("请求失败！不能设置同步同一个群的消息！");
                        return true;
                    }
                    $r = $this->setDistribution($origin, $target);
                    if ($r === null) {
                        $this->reply("请求失败！炸毛可能不在对方群里！");
                        return true;
                    }
                    $this->reply("同步设置成功！");
                    return true;
                }
                else {
                    if ($origin != "本群" && $target != "本群") {
                        $this->reply("不可以请求两个其他的群进行转发消息!");
                        return true;
                    }
                    $origin = $origin == "本群" ? $this->data["group_id"] : $origin;
                    $target = $target == "本群" ? $this->data["group_id"] : $target;
                    if ($origin == $target) {
                        $this->reply("请求失败！不能设置同步同一个群的消息！");
                        return true;
                    }
                    //发送同步请求
                    $r = $this->requestDistribution($origin, $target, $origin == $this->data["group_id"] ? 0 : 1);
                    if ($r === null) {
                        $this->reply("请求失败！炸毛可能不在对方群里！");
                    }
                    elseif ($r === false) {
                        $this->reply("请求失败！对方群还有未处理的请求！");
                    }
                    else {
                        $this->reply("成功发送请求！请静待对方管理员同意！");
                    }
                    return true;
                }
            default:
                return false;
        }
    }

    public function requestDistribution($origin, $target, $type){
        if ($type == 0) $need = $target;
        else $need = $origin;
        $ls = Buffer::get("group_list")[$need == $target ? $target : $origin] ?? null;
        if ($ls === null) return null;
        $msg = "「群消息同步请求」";
        $msg .= $type == 1 ? "\n有群想要转发本群的消息到目标群" : "\n有群想要转发消息到本群";
        $origin_profile = Buffer::get("group_list")[$need == $target ? $origin : $target];
        $msg .= "\n对方群：" . $origin_profile["group_name"] . "(" . $origin_profile["group_id"] . ")";
        $msg .= "\n同意请求回复：同意同步请求";
        $msg .= "\n拒绝请求回复：忽略同步请求";
        $my = Buffer::get("message_transfer_request");
        if (isset($my[$need])) return false;
        $my[$need] = [$origin => $target];
        Buffer::set("message_transfer_request", $my);
        $this->sendGroupMsg($need, $msg);
        return true;
    }

    public function setDistribution($origin, $target, $mode = true){
        if(!isset(Buffer::get("group_list")[$origin])) return null;
        if(!isset(Buffer::get("group_list")[$target])) return null;
        $ls = Buffer::get("message_transfer");
        if (!isset($ls[$origin])) $ls[$origin] = [];
        if ($mode === true) {
            if (!in_array($target, $ls[$origin])) $ls[$origin][] = $target;
            Buffer::set("message_transfer", $ls);
            $origin_profile = Buffer::get("group_list")[$origin];
            $target_profile = Buffer::get("group_list")[$target];
            $this->sendGroupMsg($origin, "「群消息转发通知」\n本群的消息将由炸毛转发到" . $target_profile["group_name"] . "(" . $target_profile["group_id"] . ")\n获取帮助回复：同步消息");
            $this->sendGroupMsg($target, "「群消息转发通知」\n群组 " . $origin_profile["group_name"] . "(" . $origin_profile["group_id"] . ") 的消息将转发到本群\n获取帮助回复：同步消息");
        }
        else {
            if (in_array($target, $ls[$origin])) {
                $k = array_search($target, $ls[$origin]);
                array_splice($ls[$origin], $k, 1);
            }
            Buffer::set("message_transfer", $ls);
            $origin_profile = Buffer::get("group_list")[$origin];
            $target_profile = Buffer::get("group_list")[$target];
            $this->sendGroupMsg($origin, "「群消息转发通知」\n本群的消息将停止转发到" . $target_profile["group_name"] . "(" . $target_profile["group_id"] . ")\n获取帮助回复：同步消息");
            $this->sendGroupMsg($target, "「群消息转发通知」\n群组 " . $origin_profile["group_name"] . "(" . $origin_profile["group_id"] . ") 的消息将停止转发到本群\n获取帮助回复：同步消息");
        }
    }

    public function msg_replace($msg, $origin_data){
        $origin_group = Buffer::get("group_list")[$origin_data["group_id"]];
        $msg = str_replace("{user_id}", $origin_data["user_id"], $msg);
        $msg = str_replace("{group_name}", $origin_group["group_name"], $msg);
        $msg = str_replace("{group_id}", $origin_data["group_id"], $msg);
        foreach ($origin_group["member"] as $k => $v) {
            if ($v["user_id"] == $origin_data["user_id"]) {
                if ($v["card"] != "") {
                    $msg = str_replace("{user_prefix}", $v["card"], $msg);
                }
                else {
                    $msg = str_replace("{user_prefix}", $v["nickname"], $msg);
                }
                break;
            }
        }
        $msg = str_replace("{msg}", $origin_data["message"], $msg);
        return $msg;
    }
}