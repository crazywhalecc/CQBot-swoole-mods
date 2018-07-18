<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/7/18
 * Time: 5:26 PM
 */


/**
 * Class Lexicon
 * 模块名称: 个人词库
 * 模块版本: 1.0
 * 模块简介: 此模块是为用户添加个人词库而设计的，用户可以自行添加自己的关键词回复内容
 */
class Lexicon extends ModBase
{
    /** @var string */
    const word_img_path = "***HERE your coolq directory/***" . "word_data/";
    //这里需要将目录位置改为你的酷Q根目录⬆

    public function __construct(CQBot $main, $data, bool $mod_cmd = false) {
        parent::__construct($main, $data, $mod_cmd);
        $this->call_task = true;
        if ($this->execute(explode(" ", $data["message"])) !== false) return;
        $user = $this->getUser();
        $status = $user->getBuffer();
        if ($status != [] && $status !== null) {
            switch ($status["word_type"]) {
                case "lexicon":
                    $msg = $data["message"];
                    while (($cq = CQUtil::getCQ($msg)) !== null) {
                        if ($cq["type"] == "image" && isset($cq["params"]["url"])) {
                            if (!file_exists(self::word_img_path . $cq["params"]["file"])) {
                                file_put_contents(self::word_img_path . $cq["params"]["file"], file_get_contents($cq["params"]["url"]));
                            }
                            $cqs = mb_strpos($msg, "[");
                            $cqe = mb_strpos($msg, "]");
                            $sub = mb_substr($msg, $cqs, $cqe + 1);
                            $msg = str_replace($sub, "{{img:" . $cq["params"]["file"] . "}}", $msg);
                        } else break 2;
                    }
                    $key_word = $status["key"];
                    $type = $status["type"];
                    $value_word = $msg;
                    $ls = $user->getWordStatus();
                    switch ($type) {
                        case "insert":
                            if (isset($ls[$key_word])) {
                                $this->reply("成功更新词条 " . $ls["key"]);
                            } else {
                                $this->reply("成功将内容添加到你的个人词库！\n发送：" . $key_word . " 即可发出消息哦～only you!\n使用“显示词库”来查看你的个人词库\n使用“删除词条 xxx”来删除已保存的关键词xxx");
                            }
                            $ls[$key_word] = $value_word;
                            $user->setWordStatus($ls);
                            $this->getUser()->setBuffer([]);
                            return;
                        case "plus":
                            if (!isset($ls[$key_word])) {
                                $this->reply("你没有关键词为 " . $key_word . " 的词条，请先添加后再做追加！");
                                return;
                            }
                            $v = $ls[$key_word];
                            $v .= "\n" . $value_word;
                            $ls[$key_word] = $v;
                            $user->setWordStatus($ls);
                            $this->reply("成功追加内容到词条 " . $ls["key"]);
                            $this->getUser()->setBuffer([]);
                            return;
                    }
                    break;
            }
        } else {
            $key_word = $data["message"];
            Console::debug("收到消息:" . $key_word);
            $base = base64_encode($key_word);
            $lex = $user->getWordStatus();
            if (isset($lex[$key_word]) || isset($lex[$base])) {
                Console::debug("检测匹配到词：" . $key_word);
                if (isset($lex[$base])) $value_word = $lex[$base];
                else $value_word = $lex[$key_word];
                Console::debug("将要回复内容：" . $value_word);
                $this->replaceCode($value_word);
                if (substr($value_word, 0, 8) === '[base64]') $value_word = base64_decode(substr($value_word, 8));
                if (substr($value_word, 0, 13) === '&#91;api&#93;') {
                    $url = substr($value_word, 13);
                    if (substr($url, 0, 7) != "http://") $url = "http://" . $url;
                    $url = preg_replace("/&#44;/", '?', $url, 1);
                    $url = preg_replace("/&#44;/", '&', $url);
                    $content = file_get_contents($url, false, NULL, 0, 1024);
                    if ($content == false) $content = "[请求时发生了错误]如有疑问，请联系炸毛管理员";
                    $value_word = $content;
                }
                $this->reply($value_word);
                return;
            }
        }
        return;
    }

    public function execute($it) {
        switch ($it[0]) {
            case "添加词库":
                if (count($it) < 2) {
                    $this->reply("你没有输入关键词呢！用法：\n添加词库 xxx");
                    return true;
                }
                array_shift($it);
                $l = implode(" ", $it);
                $this->getUser()->setWordStatus(["key" => $l, "type" => "insert", "word_type" => "lexicon"]);
                $this->reply("成功进入添加模式，请发送文字、语音或图片进行储存！");
                return true;
            case "删除词条":
                if (!isset($it[1])) {
                    $this->reply("你没有输入关键词呢！用法：\n删除词条 xxx");
                    return true;
                }
                array_shift($it);
                $key_word = implode(" ", $it);
                $user = $this->getUser();
                $lex = $user->getWordStatus();
                if (isset($lex[$key_word])) {
                    unset($lex[$key_word]);
                } else {
                    $s = '[base64]' . base64_encode($key_word);
                    if (isset($lex[$s])) {
                        unset($lex[$s]);
                    }
                }
                $user->setWordStatus($lex);
                $this->reply("已删除词条 " . $key_word);
                return true;
            case "追加词条":
                if (count($it) < 2) {
                    $this->reply("用法：追加词条 关键词");
                    return true;
                }
                array_shift($it);
                $it = implode(" ", $it);
                $this->getUser()->setBuffer(["key" => $it, "type" => "plus", "word_type" => "lexicon"]);
                $this->reply("成功进入追加模式，请发送文字、语音或图片进行储存！");
                return true;
            case "显示词库":
                $user = $this->getUser();
                $list = $user->getWordStatus();
                $msg = '';
                if ($list != []) {
                    foreach ($list as $key => $item) {
                        $key_word = $key;
                        $value_word = $item;
                        if (substr($key_word, 0, 8) === '[base64]') $key_word = base64_decode(substr($key_word, 8));
                        if (substr($value_word, 0, 8) === '[base64]') $value_word = base64_decode(substr($value_word, 8));
                        $value_word = preg_replace("/\[CQ:record(.*)\]/", "[语音消息]", $value_word);
                        $value_word = preg_replace("/\[CQ:at(.*)\]/", "[at某人]", $value_word);
                        $value_word = preg_replace("/\[CQ:rps(.*)\]/", "[猜拳魔法表情]", $value_word);
                        $value_word = preg_replace("/\[CQ:dice(.*)\]/", "[掷骰子魔法表情]", $value_word);
                        $value_word = preg_replace("/\[CQ:shake(.*)\]/", "[戳一戳]", $value_word);
                        $value_word = preg_replace("/\[CQ:music(.*)\]/", "[音乐]", $value_word);
                        $value_word = preg_replace("/\[CQ:share(.*)\]/", "[分享]", $value_word);
                        $value_word = preg_replace("/\[CQ:anonymous(.*)\]/", "[匿名消息]", $value_word);
                        $this->replaceCode($value_word);
                        $msg = $msg . "[" . $key_word . "]:\n" . $value_word . "\n";
                    }
                }
                if ($msg === '') $this->reply("你还没有添加任何词哦，使用“添加词库”功能进行添加");
                else $this->reply($msg);
                return true;
        }
        return false;
    }

    public function replaceCode(&$value_word) {
        while (($code = $this->getCode($value_word)) !== null) {
            if ($code["type"] == "img") {
                $file_name = $code["param"];
                $start = mb_strpos($value_word, "{{");
                $end = mb_strpos($value_word, "}}");
                $sub = mb_substr($value_word, $start, $end - $start + 2);
                $value_word = str_replace($sub, "[CQ:image,file=file://word_data/" . $file_name . "]", $value_word);
            } else break;
        }
    }

    public function getVariables($word) {
        $words = [];
        while (($pos = mb_strpos($word, "$(")) !== false) {
            if (mb_substr($word, $pos + 1, 1) == "(") {
                if (($end = mb_strpos($word, ")")) !== false && $end > ($pos + 1)) {
                    $var = mb_substr($word, $pos + 2, $end - 2);
                    $words[] = $var;
                    $word = str_replace("$(" . $var . ")", "{" . $var . "}", $word);
                } else {
                    $word = mb_substr($word, $pos + 1);
                    continue;
                }
            } else {
                $word = mb_substr($word, $pos + 1);
                continue;
            }
        }
        return $words;
    }

    public function getCode($msg) {
        if (($start = mb_strpos($msg, '{{')) === false) return null;
        if (($end = mb_strpos($msg, '}}')) === false) return null;
        $msg = mb_substr($msg, $start + 2, $end - $start - 2);
        if (mb_strpos($msg, ":") === false) return null;
        $s = explode(":", $msg);
        $type = $s[0];
        $param = $s[1];
        return ["type" => $type, "param" => $param];
    }
}