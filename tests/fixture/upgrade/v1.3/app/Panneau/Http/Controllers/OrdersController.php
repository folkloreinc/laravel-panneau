<?php

namespace App\Panneau\Http\Controllers;

use Illuminate\Http\Request;
use Panneau\Http\Controllers\ResourceController;

class OrdersController extends ResourceController
{
    protected function getResourceFromRequest(Request $request)
    {
        return app('panneau')->resource('orders');
    }

    protected function getUpdateDataFromRequest(Request $request)
    {
        return $request->only(['status']);
    }

    public function exportCsv(Request $request)
    {
        return response()->streamDownload(fn () => print('id,email'), 'orders.csv');
    }

    public function batchDelete(Request $request)
    {
        return response()->noContent();
    }
}
