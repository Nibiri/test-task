<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('sync:recover')->everyMinute();
