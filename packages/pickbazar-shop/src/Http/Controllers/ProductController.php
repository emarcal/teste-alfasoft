<?php

namespace PickBazar\Http\Controllers;

use Aws\Result;
use GraphQL\Executor\Values;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PickBazar\Database\Repositories\ProductRepository;
use PickBazar\Database\Models\Product;
use PickBazar\Database\Models\Attribute;
use PickBazar\Database\Models\AttributeValue;
use PickBazar\Database\Models\AttributeProduct;
use PickBazar\Http\Requests\ProductCreateRequest;
use PickBazar\Http\Requests\ProductUpdateRequest;
use PickBazar\Database\Models\Category;
use PickBazar\Database\Models\VariationOption;
use PickBazar\Database\Models\ZoneSoft;
use PickBazar\Database\Models\Attachment;

class ProductController extends CoreController
{
    public $repository;

    public function __construct(ProductRepository $repository)
    {
        $this->repository = $repository;
    }


    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return Collection|Product[]
     */
    public function index(Request $request)
    {
        if($request->search == "type.slug:home"){
            $limit = $request->limit ?   $request->limit : 15;
            return $this->repository->where('status','publish')->with(['type', 'categories', 'variations.attribute'])->paginate($limit);
        }else{
            $limit = $request->limit ?   $request->limit : 15;
        return $this->repository->with(['type', 'categories', 'variations.attribute'])->paginate($limit);
        }
        
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param ProductCreateRequest $request
     * @return mixed
     */
    public function store(ProductCreateRequest $request)
    {
        return $this->repository->storeProduct($request);
    }

    /**
     * Display the specified resource.
     *
     * @param $slug
     * @return JsonResponse
     */
    public function show($slug, Request $request)
    {
        try {
            $limit = isset($request->limit) ? $request->limit : 10;
            $product = $this->repository
                ->with(['type', 'categories', 'variations.attribute.values', 'variation_options'])
                ->findOneByFieldOrFail('slug', $slug);
            $product->related_products = $this->repository->fetchRelated($slug, $limit);
            return $product;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Product not found!'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param ProductUpdateRequest $request
     * @param int $id
     * @return array
     */
    public function update(ProductUpdateRequest $request, $id)
    {
        return $this->repository->updateProduct($request, $id);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param $id
     * @return JsonResponse
     */
    public function destroy($id)
    {
        try {
            return $this->repository->findOrFail($id)->delete();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Product not found!'], 404);
        }
    }

    public function relatedProducts(Request $request)
    {
        $limit = isset($request->limit) ? $request->limit : 10;
        return $this->repository->fetchRelated($request->slug, $limit);
    }

    public function menuZS(Request $request){
        

        // Criar ou Atualizar Atributos 
        echo "Criar ou Atualizar Atributos <br>";
        $this->syncAttributes($request['attribute_groups']);
        echo "-------------------------------------------<br>";
        // Criar ou Atualizar Valores de Atributos
        echo "Criar ou Atualizar Valores de Atributos <br>";
        $this->syncAttributeValues($request['attributes']);
        echo "-------------------------------------------<br>";
        // Mesclar Valores de Atributos && Atributos 
        echo "Mesclar Valores de Atributos && Atributos  <br>";
        $this->mergeAttributes($request['attribute_groups']);
        echo "-------------------------------------------<br>";
        // Criar ou Atualizar Produtos
        echo "Criar ou Atualizar Produtos  <br>"; 
        $this->syncProducts($request['products']);
        echo "-------------------------------------------<br>";
        // Criar Familias
        echo "Criar Familias  <br>"; 
        $this->syncFamilies($request['families']);
        echo "-------------------------------------------<br>";
        // Mesclar Categorias && Produtos
        echo "Mesclar Categorias && Produtos  <br>"; 
        $this->mergeFamilies($request['families']);
        echo "-------------------------------------------<br>";

        return true;
    }
    public function syncAttributeValues($sync){
        // Criar ou Atualizar Valores de Atributos ---------------------------------------
        echo "----<br>";
        
        array_multisort(array_column($sync, 'id'), SORT_ASC, $sync);

        foreach($sync as $atrvalue){
            if(AttributeValue::where('sync_id',$atrvalue['id'])->count() == 0){
                // Criar 
                if(isset($atrvalue['price_impact'])){
                    $price = $atrvalue['price_impact'];
                }else{
                    $price = 0;
                }
                $attributevalue = AttributeValue::create([
                    "sync_id"       => $atrvalue['id'],
                    "sync_price"    => $price,
                    "sync_tax_rate" => $atrvalue['tax_rate'],
                    "value"         => $atrvalue['name'],
                    "attribute_id"  => 1
                ]);
                echo "Pela inexistencia o valor ".$atrvalue['name']." foi criado <br>";
            }else{
                // Atualizar 
                $attributevalue = AttributeValue::where('sync_id',$atrvalue['id'])->first();
                $attributevalue->value = $atrvalue['name'];
                $attributevalue->save();
                echo "Atributo ".$attributevalue->name." atualizado para ".$atrvalue['name']."<br>";
            }
        }
    } 
    public function syncAttributes($sync){

        array_multisort(array_column($sync, 'id'), SORT_ASC, $sync);
        // Criar ou Atualizar Atributos -------------------------------------------------
        echo "----<br>";
        foreach($sync as $atrgr){
            if(Attribute::where('sync_id',$atrgr['id'])->count() == 0){
                // Criar 
                $attribute = Attribute::create([
                    "sync_id" => $atrgr['id'],
                    "name"    => $atrgr['name']
                ]);
                echo "Pela inexistencia o atributo ".$atrgr['name']." foi criado<br>";
            }else{
                // Atualizar 
                $attribute = Attribute::where('sync_id',$atrgr['id'])->first();
                $attribute->name = $atrgr['name'];
                $attribute->save();
                echo "Atributo ".$attribute->name." atualizado para ".$atrgr['name']."<br>";
            }
        }
        
    }
    public function syncProducts($sync){
        // Criar ou Atualizar Produtos ----------------------------------------------------
        echo "----<br>";

        array_multisort(array_column($sync, 'id'), SORT_ASC, $sync);

        $zs = new ZoneSoft(env('ZONESOFT_NIF'),env('ZONESOFT_USER'),env('ZONESOFT_PASSWORD'),env('ZONESOFT_STORE'),'','');

        foreach($sync as $prod){
            
            if($prod['price']){
                $price = $prod['price']/100;
            }else{
                $price = 0;
            }
            if(Product::where('sync_id',$prod['id'])->count() == 0){
                // Criar 
                $product = Product::create([
                    "sync_id"      => $prod['id'],
                    "name"         => ucfirst($prod['name']),
                    "slug"         => $this->slugify($prod['name']),
                    "price"        => $price,
                    //"image"        => $img,
                    "quantity"     => 10000,
                    "unit"         => "Unid",
                    "product_type" => "simple",
                    "status"       => 'publish',
                    "type_id"       => 1
                ]);
                echo "Pela inexistencia o produto ".$prod['name']." foi criado<br>";
            }else{
                // Atualizar 
                
                // Imagem
                if($zs->autenticate()){
                    if($zs->getImage($prod['id']) != null){
                        $img = $this->uploadBase64($zs->getImage($prod['id']));
                    }else{
                        $img = null;
                    }
                    
                }else{
                    $img = null; 
                }

                $product = Product::where('sync_id',$prod['id'])->first();
                $product->name = ucfirst($prod['name']);
                $product->slug = $this->slugify($prod['name']);
                $product->image = $img;
                $product->price = $price;
                $product->quantity = 100000;
                $product->unit = 'Unid';
                $product->max_price = NULL;
                $product->min_price = NULL;
                $product->product_type = 'simple';
                $product->save();

                echo "Produto ".$product->name." atualizado para ".$prod['name']."<br>";
            }
            if($prod['attributes_groups']){
                $this->mergeAtributeProducts($prod);
            }
            
        }
    }
    public function mergeAtributeProducts($sync){
        // Criar ou Atualizar Produtos ----------------------------------------------------
        echo "----<br>";
        $aatr = [];
        $prod = Product::where('sync_id',$sync['id'])->first();
        foreach($sync['attributes_groups'] as $atrgr){
            $attribute = Attribute::where('sync_id',$atrgr)->first();
            $aatrvalues = [];
            foreach($attribute->values as $atrvalue){
                $aatrvalues[] = array(
                    'id'=>$atrvalue->sync_id,
                    'name'=>$atrvalue->value,
                    'price'=>$atrvalue->sync_price,
                    'rate'=>$atrvalue->sync_tax_rate,
                    'attribute_id'=>$attribute->sync_id,
                    'attribute_name'=>$attribute->name
                );
                if(AttributeProduct::where('attribute_value_id',$atrvalue->id)->where('product_id',$prod->id)->count() == 0){
                    // Anexar Valor ao Produto
                    AttributeProduct::create([
                        "attribute_value_id"=>$atrvalue->id,
                        "product_id"=>$prod->id
                    ]);
                    echo "Valor de atributo ".$atrvalue->value." anexado ao produto ".$prod->name." <br>";
                }
            }
            array_multisort(array_column($aatrvalues, 'id'), SORT_ASC, $aatrvalues);

            $aatr[] = array('id'=>$atrgr,'name'=>$attribute->name,'values'=>$aatrvalues);

            array_multisort(array_column($aatr, 'id'), SORT_ASC, $aatr);
        }
        foreach($this->combineAttributes($aatr) as $comb){
            $optionvalues = [];

            $var_info = array('title'=>'','bar'=>'/','price'=>0);
            foreach($comb as $bar => $t){
                if ($bar === array_key_first($comb)) {
                    $var_info['bar'] = '';
                    // $varinfo['title'] .= $prod->name." - ";
                }else{
                    $var_info['bar'] = '/';
                }
                $var_info['title'] .= $var_info['bar'].$t['name'];
                $var_info['price'] = $var_info['price'] + $t['price'];
            }

            foreach($comb as $values){
                $optionvalues[] = array(
                    'name'=> $values['attribute_name'],
                    'value'=> $values['name'],
                    'price'=> $values['price'],
                    'rate'=> $values['rate'],
                    'id'=> $values['id'],
                );
            }
            if($sync['price'] > 0){
                $option_price = $sync['price'];
            }else{
                $option_price = $var_info['price'];
            }
            $prod = Product::where('sync_id',$sync['id'])->first();
            if(VariationOption::where('title',$var_info['title'])->count() == 0){
                // Não existe
                $variationoption = VariationOption::create([
                    "title"         =>  $var_info['title'],
                    //"quantity"      =>  $prod->quantity,
                    "quantity"      =>  10000,
                    //"is_disable"    =>  $isdisabled,
                    "price"         =>  $option_price,
                    "product_id"    =>  $prod->id,
                    "options"       =>  json_encode($optionvalues)
                ]);
                echo "Montou a variação ".$var_info['title']."<br>";
            }else{
                // Existe
                $variationoption = VariationOption::where('title',$var_info['title'])->first();
                if(VariationOption::where('title',$var_info['title'])->count() > 1){
                    VariationOption::where('title',$var_info['title'])->where('id','!=',$variationoption->id)->delete();
                }
                //$variationoption->quantity = $prod->quantity;
                $variationoption->quantity = 10000;
                //$variationoption->is_disable = $isdisabled;
                $variationoption->price = $option_price;
                $variationoption->product_id = $prod->id;
                $variationoption->options =  json_encode($optionvalues);
                $variationoption->save();
                echo "Atualizou a variação ".$var_info['title']."<br>";
            }
            // Geting Total
            $total = 0;
            $total_array = [];
            foreach($this->combineAttributes($aatr) as $key=> $c){
                foreach($c as $sum){
                    $total = $total + $sum['price'];
                }
                $total_array[$key] = $total;
                $total = 0;
            }
            
            $prod->product_type = "variable";
            if($sync['price'] > 0){
                $prod->max_price = $sync['price'];
                $prod->min_price = $sync['price'];
            }else{
                $prod->max_price = $this->getMaxPrice($total_array);
                $prod->min_price = $this->getMinPrice($total_array);
            }
            $prod->price = NULL;
            $prod->save();

            echo "Atualizar Produto Variavel<br>";
            
        }
    }
    public function combineAttributes($set)
    {
        $array = $set;
        if (!$set) {
            return array(array());
        }
        $subset = array_shift($set)['values'];
        $cartesianSubset = $this->combineAttributes($set);
        $result = array();
        foreach ($subset as $value) {
            foreach ($cartesianSubset as $p) {
                array_unshift($p, 
                    array(
                        'id'=>$value['id'],
                        'name'=>$value['name'],
                        'price'=>$value['price'],
                        'rate'=>$value['rate'],
                        'attribute_id'=>$value['attribute_id'],
                        'attribute_name'=>$value['attribute_name'],
                    )
                );
                $result[] = $p;
            }
    }
        return $result;        
    }

    public function syncFamilies($sync){
        // Criar Familias ------------------------------------------------------------
        echo "----<br>";
        foreach($sync as $fam){
            if(Category::where('name',ucfirst($fam['name']))->whereNull('parent')->count() == 0){
                // Criar 
                $category = Category::create([
                    "name"=>ucfirst($fam['name']),
                    "slug"=>$this->slugify($fam['name']),
                    "type_id"=>1
                ]);
                echo "Pela inexistencia a categoria ".$fam['name']." foi criada<br>";
            }else{
                $category = Category::where('name',ucfirst($fam['name']))->whereNull('parent')->first();
                echo "A categoria ".$fam['name']." foi selecionada<br>";
            }
            foreach($fam['subfamilies'] as $subfam){
                // Verificar se é Familia ou Subfamilia
                if($fam['name'] != $subfam['name']){
                    if(Category::where('name',ucfirst($subfam['name']))->whereNotNull('parent')->count() == 0){
                        // Criar 
                        $subcategory = Category::create([
                            "name"=>ucfirst($subfam['name']),
                            "slug"=>$this->slugify($fam['name'])."-".$this->slugify($subfam['name']),
                            "type_id"=>1,
                            "parent"=>1
                        ]);
                        echo "A sub categoria ".$subfam['name']." foi criada. <br>";
                    }else{
                        $subcategory = Category::where('name',$subfam['name'])->whereNotNull('parent')->first();
                        echo "A sub categoria ".$subfam['name']." foi selecionada. <br>";
                    }
    
                    $subcategory->parent = $category->id;
                    $subcategory->save();
                    echo "A sub subcategoria ".$subfam['name']." foi anexada a ".$fam['name'].". <br>";                    
                }
            }
        }
    }
    public function mergeAttributes($sync){
        //Mesclar Valores de Atributos && Atributos -------------------------------------
        echo "----<br>";
        foreach($sync as $atrgr){
            foreach($atrgr['attributes'] as $atrvalue){
                $attributevalue = AttributeValue::where('sync_id',$atrvalue)->first();
                $attributevalue->attribute_id = Attribute::where('sync_id',$atrgr['id'])->first()->id;
                $attributevalue->save();
                echo "Valor ".$attributevalue->value." mesclado com o Atributo ".$atrgr['name']."<br>";
            }
        }
    }
    public function mergeFamilies($sync){
        // Mesclar Categorias && Produtos -------------------------------------
        echo "----<br>";
        foreach($sync as $fam){
            foreach($fam['subfamilies'] as $subfam){
                // Verificar se é Familia ou Subfamilia
                if($fam['name'] == $subfam['name']){
                    $attach_cattegory = Category::where('name',$subfam['name'])->whereNull('parent')->first();
                }else{
                    $attach_cattegory = Category::where('name',$subfam['name'])->whereNotNull('parent')->first();
                }
                foreach($subfam['products'] as $prod){
                    $product = Product::where('sync_id',$prod)->first();
                    if($product){
                        if(!isset($product->categories)){
                            $product->categories()->attach($attach_cattegory);
                            echo "A sub categoria/sucategoria ".$subfam['name']." foi mesclada ao produto ".$product->name.". <br>";
                        }else{
                            if(!$this->existInColumn($product->categories,'name',$subfam['name'])){
                                $product->categories()->attach($attach_cattegory);
                                echo "A sub categoria/sucategoria ".$subfam['name']." foi mesclada ao produto ".$product->name.". <br>";
                            }
                        }
                    }
                }
            }
        }
    }
    public function existInColumn($array, $column, $term){
        foreach($array as $a){
            if($a[$column] == $term){
                return true;
            }
        }
    }
    
    public function uploadBase64($string){
        // Upload de Ficheiro Base 64
        $urls = [];
        $attachment = new Attachment;
        $attachment->save();
        $attachment->addMediaFromBase64($string)->toMediaCollection();
        foreach ($attachment->getMedia() as $image) {
            $converted_url = [
                'thumbnail' => $image->getUrl(),
                'original' => $image->getUrl(),
                'id' => $attachment->id
            ];
        }
        $urls[] = $converted_url;
        return $urls[0];
    }
    public function sync(){
        $product = Product::where('sync_id',5)->first();
        return $product->variation_options;
    }

    public function slugify($text, string $divider = '-')
    {
      $text = preg_replace('~[^\pL\d]+~u', $divider, $text);
      $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
      $text = preg_replace('~[^-\w]+~', '', $text);
      $text = trim($text, $divider);
      $text = preg_replace('~-+~', $divider, $text);
      $text = strtolower($text);
    
      if (empty($text)) {
        return 'n-a';
      }
      return $text;
    }
    public function getMaxPrice($array){
        rsort($array);
        return $array[0];
    }
    public function getMinPrice($array){
        sort($array);
        return $array[0];
    }
}

