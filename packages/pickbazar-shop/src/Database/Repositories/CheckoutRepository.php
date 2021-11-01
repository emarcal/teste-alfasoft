<?php


namespace PickBazar\Database\Repositories;

use Exception;
use PickBazar\Database\Models\Product;
use PickBazar\Database\Models\Tax;
use PickBazar\Database\Models\Shipping;
use PickBazar\Database\Models\Settings;
use Illuminate\Support\Facades\Log;
use PickBazar\Database\Models\VariationOption;

class CheckoutRepository
{

    public function verify($request)
    {
        $unavailable_products = $this->checkStock($request['products']);
        $amount = $this->getOrderAmount($request, $unavailable_products);
        $shipping_charge = $this->calculateShippingCharge($request, $amount);
        $tax = $this->calculateTax($request, $shipping_charge, $amount);
        return [
            'total_tax'            => $tax,
            'shipping_charge'      => $shipping_charge,
            'unavailable_products' => $unavailable_products
        ];
    }

    public function getOrderAmount($request, $unavailable_products)
    {
        if (count($unavailable_products)) {
            return $this->calculateAmountWithAvailable($request['products'], $unavailable_products);
        }
        return  $request['amount'];
    }

    public function calculateTax($request, $shipping_charge, $amount)
    {
        $tax_class = $this->getTaxClass($request);
        if ($tax_class) {
            return $this->getTotalTax($amount, $tax_class, $shipping_charge);
        }
        return $tax_class;
    }

    public function calculateAmountWithAvailable($products, $unavailable_products)
    {
        $amount = 0;
        foreach ($products as $product) {
            if (!in_array($product['product_id'], $unavailable_products)) {
                $amount += $product['subtotal'];
            }
        }
        return $amount;
    }

    public function calculateShippingCharge($request, $amount)
    {
        try {
            $settings = Settings::first();
            $class_id = $settings['options']['shippingClass'];

            

            if ($class_id) {
                //$shipping_class = Shipping::find($class_id);
                $from = $settings['options']['site']['address'];
                $to = $request->billing_address['street_address'].' '.$request->billing_address['zip'].' '.$request->billing_address['city'].' '.$request->billing_address['country'];
                $distance = json_decode(@file_get_contents('https://www.mapquestapi.com/directions/v2/route?key=z4oUxuZk2DKCB3VqPZjrN3e9YbjSIGTe&from='.urlencode($from).'&to='.urlencode($to).'&maxRoutes=1&timeOverage=100&routeType=shortest&unit=k'), true)['route']['distance'];
                
                 //$shipping_class = Shipping::find(1);
                //$shipping_class->amount = $distance;

                /*$fail = array(
                    "id"          => 1,
                    "name"        => "1º Taxa",
                    "amount"      => 98765,
                    "min_km"      => "5",
                    "max_km"      => "10",
                    "is_global"   => "1",
                    "type"        => "fixed",
                    "created_at"  => "2021-08-23T17:03:33.000000Z",
                    "updated_at"  => "2021-08-23T20:27:51.000000Z"
                );*/
                
               

                //return $this->getShippingCharge($shipping_class, $amount);
                


                
                
                $i=2;
                $choosed=0;
                while($choosed != 1){
                    $shipping = Shipping::find($i);

                    if($shipping){
                        // Verificar se a distancia está dentre
                        if($distance >= $shipping->min_km && $distance <= $shipping->max_km){
                            $choosed=1;
                            return $this->getShippingCharge($shipping, $amount);
                        }
                    }else{
                        $shipping = Shipping::find(1);
                        //$shipping->amount = $distance;
                        return $this->getShippingCharge($shipping, $amount);
                    }
                    $i++;
                }




            } else {
                return $this->calculateShippingChargeByProduct($request['products']);
            }
        } catch (\Throwable $th) {
            return 0;
        }
    }

    protected function calculateShippingChargeByProduct($products)
    {
        $total_charge = 0;
        foreach ($products as $product) {
            $total_charge += $this->calculateEachProductCharge($product['product_id'], $product['subtotal']);
        }
        return $total_charge;
    }

    protected function calculateEachProductCharge($id, $amount)
    {
        $product = Product::with('shipping')->findOrFail($id);
        if (isset($product->shipping)) {
            return $this->getShippingCharge($product->shipping, $amount);
        }
        return 0;
    }

    public function checkStock($products)
    {
        $unavailable_products = [];
        foreach ($products as $product) {
            if (isset($product['variation_option_id'])) {
                $is_not_in_stock = $this->isVariationInStock($product['variation_option_id'], $product['order_quantity']);
            } else {
                $is_not_in_stock = $this->isInStock($product['product_id'], $product['order_quantity']);
            }
            if ($is_not_in_stock) {
                $unavailable_products[] = $is_not_in_stock;
            }
        }
        return $unavailable_products;
    }

    protected function isInStock($id, $order_quantity)
    {
        try {
            $product = Product::findOrFail($id);
            if ($order_quantity > $product->quantity) {
                return $id;
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    protected function isVariationInStock($variation_id, $order_quantity)
    {
        try {
            $variationOption = VariationOption::findOrFail($variation_id);
            if ($order_quantity > $variationOption->quantity) {
                return $variationOption->product_id;
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    protected function getShippingCharge($shipping_class, $amount)
    {
        switch ($shipping_class->type) {
            case 'fixed':
                return $shipping_class->amount;
                break;
            case 'free':
                return 0;
                break;
            case 'percentage':
                return ($shipping_class->amount * $amount) / 100;
                break;
        }
        return 0;
    }

    protected function getTaxClass($request)
    {
        try {
            $settings = Settings::first();

            // Get tax settings from settings
            $tax_class = $settings['options']['taxClass'];
            return Tax::find($tax_class);
        } catch (\Throwable $th) {
            return 0;
        }

        // switch ($tax_type) {
        //     case 'global':
        //         return Tax::where('is_global', '=', true)->first();
        //         break;
        //     case 'billing_address':
        //         $billing_address = $request['billing_address'];
        //         return $this->getTaxClassByAddress($billing_address);
        //         break;
        //     case 'shipping_address':
        //         $shipping_address = $request['shipping_address'];
        //         return $this->getTaxClassByAddress($shipping_address);
        //         break;
        // }
    }

    protected function getTaxClassByAddress($address)
    {
        return Tax::where('country', '=', $address['country'])
            ->orWhere('state', '=', $address['state'])
            ->orWhere('city', '=', $address['city'])
            ->orWhere('zip', '=', $address['zip'])
            ->orderBy('priority', 'asc')
            ->first();
    }

    protected function getTotalTax($amount, $tax_class, $shipping_charge)
    {
        $tax = ($amount * $tax_class->rate) / 100;
        // if ($tax_class->on_shipping) {
        //     $tax += $shipping_charge;
        // }
        return $tax;
    }
}
