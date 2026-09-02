<?php
use Illuminate\Support\Facades\Schedule;

// 3rd of every month, 07:00 Brisbane: email admin last month's sales (ex GST).
// (SendGrid paused — command records the figures via AdminNotifier for now.)
Schedule::command('reports:monthly-sales')
    ->monthlyOn(3, '07:00')
    ->timezone('Australia/Brisbane');
