<?php

// Syncronização V1 Caracteristicas

public function sync(Request $request)
    {
        $zs = new ZoneSoft('516497618','admin','123zone','1','','');
       
        if($zs->autenticate()){

                return $allP = $zs->getProducts();
                die;
                foreach ($allP as $p){
                   
                    if($p['obs'] == "ldd"){
                        // NIVEL 1 FAMILIAS & SUBFAMILIAS
                        $ncategory = FALSE;
                        echo "Produto: ".$p['codigo']."<br>";
                        if($p['familia']){
                            echo "Encontrou a Familia: ".$p['familia']."<br>";
                            $family = $zs->getFamily($p['familia']);
                            if(Category::where('sync_id',$p['familia'])->whereNull('parent')->count() == 0){
                                // Não
                                echo "Não Existe, então criou Categoria: ".$p['familia']."<br>";
                                // Criar Categoria
                                $category = Category::create([
                                    "sync_id"=>$family['codigo'],
                                    "sync_date"=>$family['lastupdate'],
                                    "name"=>ucfirst($family['descricao']),
                                    "slug"=>$this->slugify($family['descricao']),
                                    "type_id"=>1
                                ]);
                                echo "Criou Categoria<br>";
                                $ncategory = TRUE;
                            }else{
                                // Sim
                                echo "Existe a Categoria: ".$p['familia']."<br>";
                                // Atualizar Familia
                                $category = Category::where('sync_id',$p['familia'])->whereNull('parent')->first();
                                // Verificar se existe atualização

                                if(isset($family['lastupdate'])){
                                    if($family['lastupdate'] != $category->sync_date){
                                        echo "Existe atualização da Familia: ".$p['familia']."<br>";
                                        // Se SIM Atualiza
                                        $category->sync_date = $family['lastupdate'];
                                        $category->name = ucfirst($family['descricao']);
                                        $category->slug = $this->slugify($family['descricao']);
                                        $category->type_id = 1;
                                        $category->save();
                                    }else{
                                        echo "Ignorar Familia: ".$p['familia']."<br>";
                                    }
                                }
                                
                            }
                        }
                        $nsubcategory = FALSE;
                        if($p['subfam']){
                            echo "Encontrou a Familia: ".$p['subfam']."<br>";
                            $subfamily = $zs->getSubFamily($p['subfam']);
                            if(Category::where('sync_id',$p['subfam'])->whereNotNull('parent')->count() == 0){
                                // Não
                                // Criar Sub Categoria
                                $subcategory = Category::create([
                                    "sync_id"=>$subfamily['codigo'],
                                    "sync_date"=>$subfamily['lastupdate'],
                                    //"parent"=>$p['familia'],
                                    "name"=>ucfirst($subfamily['descricao']),
                                    "slug"=>$this->slugify($subfamily['descricao']),
                                    "type_id"=>1
                                ]);
                                $subcategory->parent = Category::where('sync_id',$p['familia'])->whereNull('parent')->first()->id;
                                $subcategory->save();
                                $nsubcategory = TRUE;
                            }else{
                                // Sim
                                // Atualizar Sub Familia
                                $subcategory = Category::where('sync_id',ucfirst($p['subfam']))->whereNotNull('parent')->first();
                                // Verificar se existe atualização
                                if($subfamily['lastupdate'] != $subcategory->sync_date){
                                    echo "Existe atualização da Sub Familia: ".$p['subfam']."<br>";
                                    // Se SIM Atualiza
                                    $subcategory->sync_date = $subfamily['lastupdate'];
                                    $subcategory->name = ucfirst($subfamily['descricao']);
                                    $subcategory->parent = Category::where('sync_id',$p['familia'])->whereNull('parent')->first()->id;
                                    $subcategory->slug = $this->slugify($subfamily['descricao']);
                                    $subcategory->type_id = 1;
                                    $subcategory->save(); 
                                }else{
                                    echo "Ignorar Sub Familia: ".$p['subfam']."<br>";
                                }
                            }
                        }
                        // NIVEL 2 PRODUTOS
                        if(Product::where('sync_id',$p['codigo'])->count() == 0){
                            // Não
                            // Criar Produto
                            echo "Não existe produto, portanto criou o produto: ".$p['codigo']."<br>";
                            if($p['foto']){
                                $image = $this->uploadBase64($p['foto']);
                            }else{
                                $image = null;
                            }
                            $sync_product = Product::create([
                                "sync_id"      =>$p['codigo'],
                                "sync_date"    =>$p['lastupdate'],
                                "name"         =>ucfirst($p['descricao']),
                                "slug"         =>$this->slugify($p['descricao']),
                                //"quantity"     =>$p['qtdstock'],
                                "quantity"     =>$zs->getStock($p['codigo'],FALSE),
                                "unit"         =>$zs->getUnity($p['unidade']),
                                "price"        =>round($p['pvp2'],1),
                                "product_type" =>"simple",
                                "image"=> $image,
                                "status"       => "publish",
                                "type_id"      => 1
                            ]);
                            echo "---------<br>";
                            // Sincronizar Características
                            if($p['caracteristicas']){
                                echo "Encontrou caracteristicas<br>";
                                $this->syncVarations($zs, $sync_product, $p['caracteristicas'], $p['lastupdate']);
                            }else{
                                echo "Não existem caracteristicas<br>";
                            }
                            // Sincronizar Categoria com o Produto
                            // Se existir Sub Familia
                            if($p['subfam']){
                                // Sub Categoria
                                echo "Anexou Sub Categoria : ".$subcategory->id."<br>";
                                $sync_product->categories()->attach($subcategory);
                            }else{
                                // Categoria
                                echo "Anexou Categoria : ".$category->id."<br>";
                                $sync_product->categories()->attach($category);
                            }
                        }else{
                            // Sim
                            // Atualiza Produto;
                            // Atualizar o produto se existir
                            $sync_product = Product::where('sync_id',$p['codigo'])->first();
                            if($p['lastupdate'] != $sync_product->sync_date){
                                echo "Atualiza Produto ".$p['codigo']."<br>";
                                $sync_product->name = ucfirst($p['descricao']);
                                
                                $sync_product->slug = $this->slugify($p['descricao']);
                               
                                if(!$p['caracteristicas']){
                                    $sync_product->sync_date = $p['lastupdate'];
                                }
                               
                                //$sync_product->quantity = $p['qtdstock'];
                                $sync_product->quantity = $zs->getStock($p['codigo'],FALSE);
                                $sync_product->price = round($p['pvp2'],1);
                                $sync_product->unit = $zs->getUnity($p['unidade']);
                                if($p['foto']){
                                    $sync_product->image = $this->uploadBase64($p['foto']);
                                }
                                $sync_product->save();
                                echo "---------<br>";


                                // Sincronizar Características
                                if($p['caracteristicas']){
                                    echo "Encontrou caracteristicas<br>";
                                    $this->syncVarations($zs, $sync_product, $p['caracteristicas'], $p['lastupdate']);
                                }else{
                                    echo "Não existem caracteristicas<br>";
                                }

                                if($ncategory == TRUE || $nsubcategory == TRUE){
                                    if($p['subfam']){
                                        // Sub Categoria
                                        echo "Anexou Sub Categoria : ".$subcategory->id."<br>";
                                        $sync_product->categories()->attach($subcategory);
                                    }else{
                                        // Categoria
                                        echo "Anexou Categoria : ".$category->id."<br>";
                                        $sync_product->categories()->attach($category);
                                    }
                                }
                            }else{
                                echo "Ignorar Produto: ".$p['codigo']."<br>";
                            }
                        }
                        echo "--------------<br>";
                        echo "<br>";
                    }
            }
        }else {
            echo 'Erro de login';
        }
    }

    public function syncVarations($zs, $prod, $char, $update){
        // Listar Caracteristicas


        foreach($char as $c){
            $optionvalues = [];
            foreach($c['valores'] as $v){
                // Existe Atributo?
                $getchar = $zs->getCharacteristic($v['codigo_caracteristica']);
                if(Attribute::where('sync_id',$getchar['id'])->count() == 0){
                   // NÃO
                   // Criar Atributo
                   $attribute = Attribute::create([
                        "sync_id"=>$getchar['id'],
                        "sync_date"=>$getchar['lastupdate'],
                        "name"=>$getchar['valor']
                    ]);
                    echo "Pela inexistencia o atributo ".$getchar['valor']." foi criado<br>";
                }else{
                    // SIM
                    $attribute = Attribute::where('sync_id',$getchar['id'])->first();
                    // Houve Atualização?
                    if($getchar['lastupdate'] != $attribute->sync_date){
                        // Atualizar
                        $attribute->sync_date = $getchar['lastupdate'];
                        $attribute->name = $getchar['valor'];
                        $attribute->save();
                        echo "Atualizou atributo ".$attribute->name."<br>";
                        // Atualizar Valores
                    }else{
                        echo "Ignorou o atributo ".$attribute->name."<br>";
                    }
                }
                foreach($getchar['caracteristicas_valor'] as $getval){
                    // Existe Valor?
                    if(AttributeValue::where('sync_id',$getval['id'])->count() == 0){
                        //NÃO
                        $attributevalue = AttributeValue::create([
                            "sync_id"=>$getval['id'],
                            "value"=>$getval['valor'],
                            "attribute_id"=>$attribute->id
                        ]);
                        echo "Pela inexistencia o valor ".$getval['valor']." foi criado <br>";
                    }else{
                        //SIM
                        $attributevalue = AttributeValue::where('sync_id',$getval['id'])->first();
                        // Atualizar
                        $attributevalue->value = $getval['valor'];
                        //$attributevalue->attribute_id = $attribute->id;
                        $attributevalue->save();
                        echo "Atualizou valor ".$getval['valor']."<br>";
                    }
                    if(AttributeProduct::where('attribute_value_id',$attributevalue->id)->where('product_id',$prod->id)->count() == 0){
                        // Anexar Valor ao Produto
                        AttributeProduct::create([
                            "attribute_value_id"=>$attributevalue->id,
                            "product_id"=>$prod->id
                        ]);
                        echo "Pela inexistencia o atributo ".$getval['valor']." foi anexado <br>";
                    }
                }
                $optionvalues[] = array(
                    'name'=> $v['descricao_caracteristica'],
                    'value'=> $v['valor']
                );
            }
            // Montar a Variação
            if($c['estado'] == 0){
                $isdisabled = 1;
            }else{
                $isdisabled = 0;
            }
            if(VariationOption::where('title',$c['descricao'])->count() == 0){
                // Não existe
                $variationoption = VariationOption::create([
                    "title"         =>  $c['descricao'],
                    //"quantity"      =>  $prod->quantity,
                    "quantity"      =>  $zs->getStock($prod->sync_id,$c['uid']),
                    "is_disable"    =>  $isdisabled,
                    "price"         =>  $c['variacao_preco_venda'],
                    "product_id"    =>  $prod->id,
                    "options"       =>  json_encode($optionvalues)
                ]);
                echo "Montou a variação ".$c['descricao']."<br>";
            }else{
                // Existe
                $variationoption = VariationOption::where('title',$c['descricao'])->first();

                if(VariationOption::where('title',$c['descricao'])->count() > 1){
                    VariationOption::where('title',$c['descricao'])->where('id','!=',$variationoption->id)->delete();
                }
                //$variationoption->quantity = $prod->quantity;
                $variationoption->quantity = $zs->getStock($prod->sync_id,$c['uid']);
                $variationoption->is_disable = $isdisabled;
                $variationoption->price = $c['variacao_preco_venda'];
                $variationoption->product_id = $prod->id;
                $variationoption->options =  json_encode($optionvalues);
                $variationoption->save();
                echo "Atualizou a variação ".$c['descricao']."<br>";
            }

            $prod->product_type = "variable";
            $prod->max_price = $this->getMaxPrice($char);
            $prod->min_price = $this->getMinPrice($char);
            $prod->sync_date = $update;
            $prod->price = NULL;
            
            $prod->save();


            echo "Atualizar Produto Variavel<br>";
            echo "---<br>";
        }
    }



    // public function combinations($arrays) {
//     $result = array(array());
//     $values = array();
//     foreach ($arrays as $property => $property_values) {
//         $tmp = array();
//         foreach ($result as $result_item) {
//             foreach ($property_values['niveismenuext'] as $property_value) {
//                 $tmp[] = array_merge($result_item, array($property => 
//                  array(
//                     'codigo'=>$property_value['codigo'],
//                     'valor'=>Product::where('sync_id',$property_value['codigo'])->first()->name,
//                 )
//             ));
//             }
//         }
//         $values[] = array('valores'=>'xxxx');
//         $result['result'] = $values;
//         //$result = $tmp;

        
//     }
//     return $result;
// }


    // public function getMaxPrice($array){
    //     $columns = array_column($array, 'variacao_preco_venda');
    //     array_multisort($columns, SORT_DESC, $array);
    //     return $array[0]['variacao_preco_venda'];
    // }

    // public function getMinPrice($array){
    //     $columns = array_column($array, 'variacao_preco_venda');
    //     array_multisort($columns, SORT_ASC, $array);
    //     return $array[0]['variacao_preco_venda'];
    // }