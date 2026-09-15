<?php

use Illuminate\Support\Facades\Route;

// Pages live in app/Modules/<Name>/routes. Until the home page is ported, the root opens the shop.
Route::redirect('/', '/sklep');
