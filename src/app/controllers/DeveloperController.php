<?php

namespace App\Controllers;

use Flight;

class DeveloperController
{
    public function swagger(): void
    {
        Flight::render('page/developer/swagger.latte', [
            'title' => 'Swagger'
        ]);
    }
}
