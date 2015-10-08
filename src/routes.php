<?php
if (!isset($root) || !isset($controller)) {
    throw new Exception ("This file can't be included outside of ManagerServiceProvider@boot!");
}

if (isset($before) && $before) {
    Route::group(['before' => $before], function () use ($root, $controller) {
        Route::controller($root, $controller);
    });
} else {
    Route::controller($root, $controller);
}
