<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Remind;
use Carbon\Carbon;

class RemindLineCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:remind_line';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'ラインでリマインドをします';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->checkRemind();
    }

    public function checkRemind()
    {
        $targetRemind = Remind::orderBy('id', 'desc')
            ->limit(1)
            ->first();

        if ($targetRemind->remind_regist_time != null && $targetRemind->remind_execute_time == null)
        {
            $now = Carbon::now();
            $remindTime = new Carbon($targetRemind->remind_regist_time);

            if ($now >= $remindTime) {
                Log::debug('該当リマインドあり');
                // LIneへ通知
                $this->sendLine($targetRemind->content);
                $targetRemind->remind_execute_time = $now;
                $targetRemind->save();
            } else
            {
                Log::debug('該当リマインドなし');
                return false;
            }
        }

    }

    public function sendLine($content)
    {
        $access_token = env('LINE_ACCESS_TOKEN');

        // メッセージ
        $messeage_data = [
            "type" => "text",
            "text" =>  "リマインド内容は「" . $content  . "」" . "ですよ！思い出しましたか？",
        ];

        $navigate_data = [
            "type" => "text",
            "text" =>  "リマインドしたい内容を教えてください！",
        ];

        // ポストデータ
        $post_data = [
            "to"       => env('TEST_USER_ID'),
            "messages" => [$messeage_data, $navigate_data]
        ];

        // curl実行
        $ch = curl_init("https://api.line.me/v2/bot/message/push");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charser=UTF-8',
            'Authorization: Bearer ' . $access_token
        ));
        $result = curl_exec($ch);
        $result = json_decode($result);
        curl_close($ch);
    }
}
