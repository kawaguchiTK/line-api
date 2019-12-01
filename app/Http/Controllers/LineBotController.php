<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Remind;
use App\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use LINE\LINEBot\Event\MessageEvent\TextMessage;
use Carbon\Carbon;
use App\library\Common;

class LineBotController extends Controller
{
    public function __construct()
    {
        $this->common = new Common();
    }


    public function parrot(Request $request)
    {
        // linebotインスタンスの取得
        $lineBot = $this->common->getLineBot();
        // イベントの取得
        $events = $this->common->getEvents($request,$lineBot);

        foreach ($events as $event) {
            if (!($event instanceof TextMessage)) {
                Log::debug('Non text message has come');
                continue;
            }

            // lineidの取得
            $lineCode = $event->getUserId();

            // user_idの取得
            $userId = $this->getUserIdFromLineId ($lineCode);

            $replyToken = $event->getReplyToken();
            $replyText = $event->getText();

            // 最後に保存したリマインドを取得
            $lastRemind = $this->getLastRemind($userId);

            // 一番初めの登録 or 次のリマインド登録
            if (empty($lastRemind) || isset($lastRemind->remind_execute_time))
            {
                $returnText =  "「" . $replyText  . "」ですね。覚えました。大体、何分後にリマインドしますか？";
                $this->saveRemindContent($replyText,$userId);
                // リマインド内容を登録後、時間を入力していない場合
            } elseif (empty($lastRemind->remind_regist_time) && !$this->checkNum($replyText))
            {
                $returnText =  "「" . $lastRemind->content  . "」の時間登録が終わっていません。大体、何分後にリマインドしますか？";
                // リマインド内容を登録後、時間を入力した場合
            } elseif (!$lastRemind->remind_regist_time && $this->checkNum($replyText))
            {
                $returnText =   "大体、".$replyText  . "分後ですね。了解しました。";
                // リマインド時間を登録
                $this->saveRemindRegistTime($lastRemind->id, $replyText);
            // 登録したリマインドが通知される前に登録しようとした場合
            } elseif (isset($lastRemind->remind_regist_time) && empty(($lastRemind->remind_execute_time)))
            {
                $returnText = "２件連続登録できません。リマインドされるまで待ってくださいね";
            }

            $lineBot->replyText($replyToken, $returnText);
        }
    }

    public function saveRemindContent($replyText,$userId)
    {
        $remind = new Remind();
        $remind->content = $replyText;
        $remind->user_id = $userId;
        $remind->save();
    }

    public function saveRemindRegistTime($lastRemindId, $replyText)
    {
        $registTime = Carbon::now()->addMinutes($replyText)->format('Y-m-d H:i:s');
        Log::debug($registTime);
        $remind = Remind::find($lastRemindId);

        $remind->remind_regist_time = $registTime;
        $remind->save();
    }

    public function getLastRemind($userId)
    {
        $remind = Remind::where('user_id',$userId)
            ->orderBy('id', 'desc')
            ->limit(1)
            ->first();

        return $remind;
    }

    public function checkNum($target)
    {
        if (is_numeric($target)) {
            return true;
        } else {
            return false;
        }
    }

    public function getUserIdFromLineId($lineCode)
    {
        $targetUser = User::where('line_code', $lineCode)
                                ->first();
        if(empty($targetUser))
        {
            // userの新規登録
            $userId =$this->registNewUser($lineCode);
            return $userId;
        } else
        {
            return $targetUser->id;
        }
    }

    public function registNewUser($lineCode)
    {
        $user = new User();
        $user->line_code = $lineCode;
        $user->save();
        return $user->id;
    }


}
