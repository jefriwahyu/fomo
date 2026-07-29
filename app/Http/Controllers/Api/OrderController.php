<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    // POST /api/orders
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $order = DB::transaction(function () use ($request) {
                $order = Order::create(['status' => 'completed']);

                foreach ($request->items as $item) {
                
                // Mengunci row produk agar transaksi lain menunggu hingga transaksi selesai
                $product = Product::where('id', $item['product_id'])
                    ->lockForUpdate()
                    ->first();

                $price = $product->is_flash_sale && $product->flash_sale_price
                    ? $product->flash_sale_price
                    : $product->price;

                // tidak akan pernah membuat stok menjadi negatif
                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Stok tidak mencukupi untuk produk: {$product->name}");
                }

                $product->decrement('stock', $item['quantity']);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $price,
                ]);
                }

                return $order;
            });

            return response()->json($order->load('items'), 201);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    // GET /api/orders/{id}
    public function show($id)
    {
        $order = Order::with('items.product')->find($id);

        if (!$order) {
            return response()->json(['message' => 'Pesanan tidak ditemukan'], 404);
        }

        return response()->json($order);
    }
}
