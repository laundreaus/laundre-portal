<?php
use Illuminate\Support\Facades\Schedule;

// 3rd of every month, 07:00 Brisbane: email admin last month's sales (ex GST).
// (SendGrid paused — command records the figures via AdminNotifier for now.)
Schedule::command('reports:monthly-sales')
    ->monthlyOn(3, '07:00')
    ->timezone('Australia/Brisbane');

// Every Monday 3pm Brisbane: last week's revenue to admins + assigned franchisees/investors.
Schedule::command('reports:weekly-revenue')
    ->weeklyOn(1, '15:00')
    ->timezone('Australia/Brisbane');

// Every day 7am Brisbane: each person's open tasks (to do / in progress / in review).
Schedule::command('reports:daily-tasks')
    ->dailyAt('07:00')
    ->timezone('Australia/Brisbane');

// Every day 8am Brisbane: email prospects sitting 14+ days in Reviewing Documents a
// prompt to book a meeting with Luke.
Schedule::command('crm:reviewing-followup')
    ->dailyAt('08:00')
    ->timezone('Australia/Brisbane');

// SMS sales snapshots to admins (Twilio).
Schedule::command('reports:sales-sms daily')->dailyAt('07:05')->timezone('Australia/Brisbane');
Schedule::command('reports:sales-sms weekly')->weeklyOn(1, '07:10')->timezone('Australia/Brisbane');   // Monday
Schedule::command('reports:sales-sms monthly')->monthlyOn(1, '07:15')->timezone('Australia/Brisbane'); // 1st of month
