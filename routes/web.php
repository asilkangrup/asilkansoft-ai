<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ANA SAYFA
|--------------------------------------------------------------------------
|
| wai.asilkansoft.com.tr açıldığında kullanıcıyı
| doğrudan AsilkanSoft AI paneline yönlendir.
|
*/

Route::redirect('/', '/admin');