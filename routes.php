<?php
use Illuminate\Support\Facades\Route;
Route::post('/coingate/webhook', [App\Extensions\Gateways\Coingate\Coingate::class, 'webhook']);