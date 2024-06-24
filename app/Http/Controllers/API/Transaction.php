<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Transaction as ModelsTransaction;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Midtrans\Config;
use Midtrans\Snap;

class Transaction extends Controller
{
    public function checkout(Request $request)
    {
        $request->validate([
            'product_name' => 'required',
            'quantity' => 'required',
            'total' => 'required'
        ]);

        DB::beginTransaction();

        try {
            $transaction = ModelsTransaction::create([
                'product_name' => $request->product_name,
                'quantity' => $request->quantity,
                'total' => $request->total,
                'payment_url' => ''
            ]);

            Config::$serverKey = config('services.midtrans.serverKey');
            Config::$isProduction = config('services.midtrans.isProduction');
            Config::$isSanitized = config('services.midtrans.isSanitized');
            Config::$is3ds = config('services.midtrans.is3ds');

            $transaction = ModelsTransaction::findOrFail($transaction->id);

            $midtrans = [
                'transaction_details' => [
                    'order_id' => $transaction->id,
                    'gross_amount' => (int) $transaction->total,
                ],
                'enabled_payments' => ['gopay', 'bank_transfer', 'other_qris'],
                'vtweb' => [],
            ];

            $paymentUrl = Snap::createTransaction($midtrans)->redirect_url;
            $transaction->payment_url = $paymentUrl;
            $transaction->save();

            DB::commit();

            return ResponseFormatter::success($transaction, "Transaksi berhasil");
        } catch (Exception $error) {
            DB::rollBack();
            return ResponseFormatter::error($error->getMessage(), "Transaksi Gagal", 500);
        }
    }
}
