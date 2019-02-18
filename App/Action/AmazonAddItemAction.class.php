<?php


import('@.Service.AmazonGenerateHtmlService');
import('@.Service.AmazonGenerateXmlService');
import('@.Service.ReadSkuDetailService');

class AmazonAddItemAction  extends AmazonCommonAction {

    private $_imgRelateTab = [
        'product' => 1,
        'variant' => 2,
    ];

    # 待刊登列表
    public function index(){

        import("ORG.Util.Page");// 导入分页类a
        $sql=" ebay_goods.copyright!=1 and ebay_goods.ebay_user !='goods_out' and ebay_goods.isuse <>'淘汰中'";// 保证不侵权
        if (isset($_GET['date']) && $_GET['date']!=null) {
            $startdate = date('Y-m-d 00:00:00', strtotime($_GET['date']));
            $endData = date('Y-m-d 23:59:59', strtotime($_GET['date']));
            $sql.= "and ebay_goods.add_time< '".$endData."' and ebay_goods.add_time> '".$startdate."')) ";
        }
        if (isset($_GET['keyWord']) && $_GET['searchType'] ==2) {
            $sql.= "and goods_sn= '".$_GET['keyWord']."' ";
        }elseif (isset($_GET['keyWord']) && $_GET['searchType'] ==1) {
            $keyWord = $_GET["keyWord"];
            $sql.= "and (ebay_goods.goods_name LIKE '%$keyWord%' or goods_sn =  '{$_GET['keyWord']}' )";
        }
        if (isset($_REQUEST['title'])) {
            $sql.= "and goods_name= '".$_GET['title']."' ";
        }
        if (isset($_REQUEST['sale_user'])) {
            $sql.= "and salesuser= '".$_GET['sale_user']."' ";
        }
        if (isset($_REQUEST['cguser'])) {
            $sql.= "and cguser= '".$_GET['cguser']."' ";
        }
        if (isset($_REQUEST['kfuser'])) {
            $sql.= "and kfuser= '".$_GET['kfuser']."' ";
        }
        if (isset($_REQUEST['type'])) {
            $sql.= "and ebay_goods.type= '".$_GET['type']."' ";
        } else {
            $sql.= "and ebay_goods.type in ('0','1') ";
        }
        if (isset($_REQUEST['categoryPid'])) {
            $categoryIdList = D('EbayGoodsCategory')->where("pid='{$_REQUEST['categoryPid']}'")->getField('id', true);
            $categoryIdList[] = $_REQUEST['categoryPid'];
            $sql.= 'and goods_category in ("' . implode('","', $categoryIdList) . '") ';
        }

        if(! empty($_REQUEST['goods_atribute'])) {
            $goods_atribute = $_REQUEST['goods_atribute'];
            $sql .= " and goods_aribute = {$goods_atribute} ";
            $this->assign('goods_atribute', $goods_atribute);
        }

        # 商品未被添加
//        $walmartItemModel            = new WalmartItemModel();
//        $walmartAlreadyKdSkuModel = new WalmartAlreadyKdSkuModel();
//        $exist_sku = $walmartItemModel->distinct(true)->getField('parent_sku', true);
//        $exist_sku1 = $walmartAlreadyKdSkuModel->getField('sku', true);
//        $exist_sku = array_merge($exist_sku, $exist_sku1);
//        if(!empty($exist_sku)) {
//            $writeSku='';
//            foreach($exist_sku as $vvv){
//                $writeSku.="'".$vvv."',";
//            }
//            $writeSku=trim($writeSku,',');
//            if(!empty($writeSku)){
//                $sql.= " and ebay_goods.goods_sn not in($writeSku)";
//            }
//        }

        if(isset($_REQUEST['store'])) {
            $store = $_REQUEST['store'];
            $goodsCountTableName = 'sys_goods_count_' . $store;
            $this->assign('store', $store);
        } else {
            $goodsCountTableName = 'sys_goods_count_196';
            $this->assign('store', 196);
        }

        if(! isset($_REQUEST['hasInventory']) || $_REQUEST['hasInventory'] == 1) {
            $sql .= " and {$goodsCountTableName}.goods_count > 0 ";
            $this->assign('hasInventory', 1);
        } else {
            $sql .= " and {$goodsCountTableName}.goods_count = 0 ";
            $this->assign('hasInventory', 0);
        }

        $goodsModel = new EbayGoodsModel();
        $sql=trim($sql,' and');
        $fieldStr = "name,BtoBnumber,goods_pic,sys_goods_step.create_time,ebay_goods.goods_id,ebay_goods.online_time,goods_name,goods_sn,goods_price,goods_cost,salesuser,cguser,addtim,lastsoldtime,isuse,goods_category,audittime,{$goodsCountTableName}.goods_count goods_count";
        $count=$goodsModel
            ->where($sql)
            ->join('left join ebay_goodscategory on ebay_goodscategory.id = goods_category')
            ->join('left join sys_goods_step on sys_goods_step.goods_id = ebay_goods.goods_id')
            ->join("inner join {$goodsCountTableName} on ebay_goods.goods_sn = {$goodsCountTableName}.sku")
            ->count();

        $stockCategories = D('EbayGoodsCategory')->where("pid=0")->getField("id,name");
        $allCategories = D('EbayGoodsCategory')->getField("id,name");
        $perpage = $_GET['page_size']?$_GET['page_size']:25;
        $Page       = new Page($count,$perpage);// 实例化分页类 传入总记录数和每页显示的记录

        if(isset($_REQUEST['sort_name']) && !empty($_REQUEST['sort_name'])) {
            $sort_name = $_REQUEST['sort_name'];
            $sort_type = $_REQUEST['sort_type'];
            switch($_REQUEST['sort_type']) {
                case 'sorting_desc':
                    $desc = $_REQUEST['sort_name'] . ' desc';
                    break;
                case 'sorting_asc':
                    $desc = $_REQUEST['sort_name'] . ' asc';
                    break;
                default :
                    $desc = "audittime desc";
            }
            $this->assign('sort_name', $sort_name);
            $this->assign('sort_type', $sort_type);
        } else {
            $desc = "audittime desc";
        }

        $skuArr = $goodsModel->where($sql)->order($desc)->limit($Page->firstRow.','.$Page->listRows)->field($fieldStr)
            ->join('left join ebay_goodscategory on ebay_goodscategory.id = goods_category')
            ->join('left join sys_goods_step on sys_goods_step.goods_id = ebay_goods.goods_id')
            ->join("inner join {$goodsCountTableName} on ebay_goods.goods_sn = {$goodsCountTableName}.sku")
            ->select();
        $userModel = D('EbayUser');
        //获取销售人员
        $sale_user_list = $userModel->getSellerNameList();
        //获取采购人员
        $purchaseList  = $userModel->getPurchaseNameList();
        //获取开发人员
        $developerList = $userModel->getDeveloperNameList();

        $goodsAttributeModel = new GoodsAttributeModel();
        $goodsAttributeList = $goodsAttributeModel->getField("id,attribute");

        $ebayStoreModel = new EbayStoreModel();
        $storeList = $ebayStoreModel->getField('id,store_name', true);

        $this->assign('purchaseList',$purchaseList);
        $this->assign('developList',$developerList);
        $this->assign('saleList',$sale_user_list);
        $this->assign('storeList',$storeList);
        $this->assign('goodsAttributeList',$goodsAttributeList);

        foreach ($_GET as $key=>$val) {
            $this->assign($key,$val);
        }

        $this->assign('erpImgDir',C('ERP_SERVER_URL'));
        $this->assign('Product_dt',$skuArr);
        $this->assign('perpage',$perpage);
        $this->assign('page',$Page->show());// 赋值分页输出
        $this->assign('allCategories', $allCategories);
        $this->assign('stockCategories', $stockCategories);
        $this->assign('date', $_GET['date']);
        $this->display();
    }

    # 刊登信息编辑页面
    public function addItem()
    {
        import('@.Service.AmazonKDService');
        $sku = $this->_get('sku');
        $categoryName = $this->_get('categoryID');
        $cateid = $this->_get('cateid');
        $categoryModel = new AmazonCategoryModel();
        $categoriesArray = $categoryModel->getCategory(0);
        $this->assign('categoriesArray',$categoriesArray);
        $this->assign('sku', $sku);
        if(empty($categoryName)) {
            $this->display();
            die;
        }
        $ebayGoods = D('EbayGoods');
        //取出该商品信息
        $fieldStr = 'BtoBnumber,goods_id,type,goods_name,goods_sn,goods_price,goods_cost,goods_weight';
        $goodsinfo = $ebayGoods
            ->field($fieldStr)
            ->where(array('goods_sn'=>$sku,'copyright'=>array('neq','1')))
            ->find();
        //通过sku查找其子sku
        if ($goodsinfo['type'] == 1) {
            $sonSkuData = $ebayGoods
                ->where(array('BtoBnumber' => $sku))
                ->getField('goods_sn,goods_price', true);
            $sonSkus = array_keys($sonSkuData);
            $this->assign('sonSkus', $sonSkus);
            $this->assign('sonSkusEn', json_encode($sonSkus));
            $this->assign('sonSkuData', json_encode($sonSkuData));
        }

        $maindata = array(
            'primary'=>$sku,
            'goods_name'=>$goodsinfo['goods_name'],
            'goods_weight'=>$goodsinfo['goods_weight'],
            'goods_price'=>$goodsinfo['goods_price'],
        );
        $generateHtmlService = new AmazonGenerateHtmlService($cateid);
        $attrHtml = $generateHtmlService->getCategoryDisplayHtmlByCateId();
        $cateNameTree = $categoryModel->getCategoryTreeByChildId($cateid);

        # 获取标题
        $kDService = new AmazonKDService();
        $titles = $kDService->getProducTitle($sku);
        # 获取产品描述信息
        $descriptData = $kDService->getProductDescription($sku);
        //格式化账号选项
        $sellerOption = $kDService->getAccountListOptions($this->_displayAccountList);
        // 获取upc数量
        $upcModel = new UpcModel();
        $upcNum = $upcModel->getUpcNum(2);

        $this->assign('goods_type', $goodsinfo['type']);
        $this->assign('sellerArray',$this->_displayAccountList);
        $this->assign('sellerArrayEn', json_encode($this->_displayAccountList));
        $this->assign('attrHtml',$attrHtml);
        $this->assign('maindata',$maindata);
        $this->assign('sellerOption',$sellerOption);
        $this->assign('cateid', $cateid);
        $this->assign('categoryName', $categoryName);
        $this->assign('cateNameTree', $cateNameTree);
        $this->assign('titles', $titles);
        $this->assign('bulletPointNum', count($descriptData['bulletPoint']) ? : 1);
        $this->assign('searchTermsNum', count($descriptData['searchTerms']) ? : 1);
        $this->assign('defaultBrand', json_encode($this->defaultBrand));
        $this->assign('upcNum', $upcNum);
        $this->assign('startDate', date('Y-m-d', strtotime('-2 day')));
        $this->assign('endDate', date('Y-m-d', strtotime('+1 year')));

        $tabPaneHtml = json_encode([$this->fetch('addItemTabPane')]);
		
		$lang_data=array('default'=>array('description'=>$descriptData['description'],'title'=>$titles[0],'bulletPoint'=>$descriptData['bulletPoint']));
		if($sku){
			$readSkuDetail = new ReadSkuDetailService($sku, 196);
			$description = $readSkuDetail->getDescription();
			foreach ($description as $key => $val) {
				$lang_data[$key]["description"]=$val['description'];
				$lang_data[$key]['bulletPoint']=explode(PHP_EOL, $val['textDescription']);
			}
        	$title = $readSkuDetail->getTitle();
			foreach ($title as $key => $val) {
				$lang_data[$key]['title']=$val[0];
			}
			
		}
        $this->assign('tabPaneHtml', $tabPaneHtml);
		$this->assign('lang_data', json_encode($lang_data));
        $this->assign('accountList', json_encode($this->_accountList));
        $this->display();
    }

    # 清库
    public function clearTab()
    {
        $amazonCategoryModel = new AmazonCategoryModel();
        $amazonCategoryModel->query('truncate sys_amazon_item');
        $amazonCategoryModel->query('truncate sys_amazon_item_image');
        $amazonCategoryModel->query('truncate sys_amazon_variant_item');
        $amazonCategoryModel->query('truncate sys_amazon_item_attr_info');
        $amazonCategoryModel->query('truncate sys_amazon_item_bulletpoint_searchterms');
        $amazonCategoryModel->query('truncate sys_amazon_submit_feed_task');
        $amazonCategoryModel->query('truncate sys_amazon_item_log');
    }

    # 刊登信息保存
    public function addItemSave()
    {
//                file_put_contents('C:\Users\Administrator.USER-20170118OD\Desktop\tmpfile/ccc.php', '<?php '.PHP_EOL.'return ' . var_export($_POST, true) . ';');
//                die;
//        $_POST = include 'C:\Users\Administrator.USER-20170118OD\Desktop\tmpfile/ccc1.php';
//        debug($_POST);die;
        import('@.Service.WalmartSkuOperateService');
        $walmartSkuOperateService = new WalmartSkuOperateService(new AmazonVariantItemModel());
        $categoryModel = new AmazonCategoryModel();
        $itemModel = new AmazonItemModel();
        $itemAttrInfoModel = new AmazonItemAttrInfoModel();
        $itemImageModel = new AmazonItemImageModel();
        $itemBulletpointSearchtermsModel = new AmazonItemBulletpointSearchtermsModel();
        $upcModel = new UpcModel();
        $variantItemModel = D('AmazonVariantItem');

        $upload_type                 = $_POST['uploadType'];
        $accountList                 = $_POST['accountList'];
        $cateid                      = $_POST['cateid'];
        $cateTree                    = $categoryModel->getCategoryTreeByChildId($cateid);
        $cateNameTree_p              = $cateTree['parentCateName'];
        $cateNameTree_cd             = $cateTree['categoryName'];
        # 获取分类属性节点树
        $nodeTrees = $this->getCategoryNodeTree($cateid, $cateNameTree_cd);
        if (! empty($cateNameTree_cd)) {
            $childCateType = $categoryModel->where(['pid' => 0, 'name' => $cateNameTree_p])->getField('relate_type');
        }
        $goods_type                  = $_POST['goods_type'];
        $parent_sku                  = $_POST['sku'];
        $allFileData = array();
        $itemModel->startTrans();
        try {
            # 判断是编辑还是新增，若是编辑则把原先信息清掉，在做保存
            $isEdit = $_POST['isEdit'];
            if($isEdit === 'yes') {
                $where = array(
                    'platform_sku' => $_POST['platform_sku'],
                );
                $info = $itemModel->where($where)->find();
                $created_user = $info['created_user'];
                $accountList = [str_replace(' ', '_', $info['account'])];
                $this->clearProductSaveDataByPlatformSku($info['id'], $info['type']);
            }
            foreach ($accountList as $account) {
                $data = $_POST[$account];
                $saveAccount = str_replace('_', ' ', $account);
                # 获取币种
                $currency = $this->_accountList[$account]['currency'];
                # 保存数据存储数组
                $itemSaveData = [];
                $bullPointSaveData = [];
                # 公共信息处理
                # 基本的item信息
                $itemSaveData['sku'] = $parent_sku;
                $parent_platform_sku = $walmartSkuOperateService->walmartSkuEncode($parent_sku);
                $itemSaveData['platform_sku'] = $parent_platform_sku;
                $itemSaveData['account'] = $saveAccount;
                $itemSaveData['type'] = $goods_type;
                $itemSaveData['category_parent'] = $cateNameTree_p;
                $itemSaveData['category_child'] = $cateNameTree_cd;
                $itemSaveData['category_id'] = $cateid;

                # 描述类信息
                $product = $data['Product'];
                $itemSaveData['title'] = $product['DescriptionData']['Title'];
                $itemSaveData['description'] = $product['DescriptionData']['Description'];
                $itemSaveData['brand'] = $product['DescriptionData']['Brand'];
                $itemSaveData['condition'] = $product['Condition'];
                $itemSaveData['recommended_browse_node'] = $product['DescriptionData']['RecommendedBrowseNode'];
                list($itemSaveData['variation_theme'], $itemSaveData['variation_theme_tree']) =
                    $this->getVariantThemeData($data);
                # 保存item信息
                $itemSaveData['product_status'] = $upload_type == 1 ? 0 : 1;
                $itemSaveData['image_status'] = $goods_type == 1 ? 0 : 4;
                $itemSaveData['status'] = $upload_type == 1 ? 0 : 1;
                $itemSaveData['kd_status'] = $upload_type == 1 ? 0 : 1;
                $itemSaveData['created_user'] = $created_user ? : session('loginName');
                $itemSaveData['created_at'] = time();
                $itemSaveData['updated_at'] = time();
                $list_id = $itemModel->add($itemSaveData);
                if (empty($list_id)) {
                    throw new Exception('基本信息保存失败! ');
                }

                foreach ($product['DescriptionData']['BulletPoint'] as $k => $bulletPoint) {
                    if ($k == 5) {
                        break;
                    }
                    $bullPointSaveData[] = ['type' => 1, 'info' => $bulletPoint];
                }
                foreach ($product['DescriptionData']['SearchTerms'] as $k => $searchTerms) {
                    if ($k == 5) {
                        break;
                    }
                    $bullPointSaveData[] = ['type' => 2, 'info' => $searchTerms];
                }
                # 亮点跟描述信息保存
                $bullPointRes = $itemBulletpointSearchtermsModel->batchSaveData($bullPointSaveData, $list_id);
                if (!$bullPointRes) {
                    throw new Exception('亮点信息保存失败！');
                }
                # 处理前台传入的属性数据
                $attrInfoSaveData = $this->dealAttrInfoData($data['Parent'], $data['Child'], $nodeTrees[1], $nodeTrees[0]);
                if (!empty($attrInfoSaveData)) {
                    if (!$itemAttrInfoModel->batchSaveData($attrInfoSaveData, $list_id)) {
                        throw new Exception('属性信息保存失败！');
                    }
                }
                # 打折时间信息
                $saleData = $data['Price'];
                # 多属性信息处理
                $skus = $data['sku'];
                foreach ($skus as $index => $sku) {
                    $imageSaveData = [];
                    $variantData = $data['sku-' . $sku];
                    # 保存成不刊登则不需要获取upc
                    if ($upload_type == 2) {
                        $upc = $upcModel->getSingleUpc(2);
                        if ($upc === false) {
                            throw new Exception('获取upc失败! ');
                        }
                    }
                    # 多属性信息
                    $platform_sku = $goods_type == 1 ? $walmartSkuOperateService->walmartSkuEncode($sku) : $parent_platform_sku;
                    $variantSaveData = [
                        'list_id' => $list_id,
                        'sku' => $sku,
                        'platform_sku' => $platform_sku,
                        'parent_sku' => $goods_type == 1 ? $parent_sku : '',
                        'parent_platform_sku' => $goods_type == 1 ? $parent_platform_sku : '',
                        'account' => $saveAccount,
                        'standard_price' => $variantData['Price']['StandardPrice'],
                        'sale_price' => $variantData['Price']['SalePrice'],
                        'start_date' => $saleData['StartDate'],
                        'end_date' => $saleData['EndDate'],
                        'currency' => $currency,
                        'inventory' => $variantData['Inventory']['FulfillmentCenterID']['Quantity'],
                        'upc' => $upc ? : '',
                        'type' => $goods_type == 1 ? 2 : 0,
                        'relation_status' => $goods_type == 1 ? 0 : 4,
                        'variant_status' => $upload_type == 1 ? 0 : 1,
                        'status' => $upload_type == 1 ? 0 : 1,
                        'kd_status' => $upload_type == 1 ? 0 : 1,
                        'created_user' => $created_user ? : session('loginName'),
                        'created_at' => time(),
                        'updated_at' => time(),
                    ];
                    # 处理前台传入的多属性数据
                    if ($goods_type == 1) {
                        $variantAttrInfo = $this->dealAttrInfoData($variantData['Parent'], $variantData['Child'], $nodeTrees[1], $nodeTrees[0]);
                        $variantSaveData['variant_info'] = json_encode($variantAttrInfo);
                    }
                    $variantId = $variantItemModel->add($variantSaveData);
                    if (empty($variantId)) {
                        throw new Exception('sku: ' . $sku . '，多属性信息保存失败！');
                    }
                    # 处理图片信息
                    # 主图
                    $imageSaveData['main'] = [
                        'image_url' => $variantData['ProductImage']['Main'],
                        'image_type' => 1,
                    ];
                    # 多属性标识图
                    if (isset($variantData['ProductImage']['Swatch'])) {
                        $imageSaveData['swatch'] = [
                            'image_url' => $variantData['ProductImage']['Swatch'],
                            'image_type' => 2,
                        ];
                    }
                    # 附图
                    if (isset($variantData['ProductImage']['PT'])) {
                        $image_type = 2;
                        $imageSaveData['PT'] = [];
                        foreach ($variantData['ProductImage']['PT'] as $key => $url) {
                            if ($key == 8) {  //附图最多8张
                                break;
                            }
                            $image_type++;
                            $imageSaveData['PT'][$key] = [
                                'image_url' => $url,
                                'image_type' => $image_type,
                            ];
                        }
                    }

                    # 多属性上传先传主sku
                    if ($goods_type == 1 && $index == 0) {
                        # 把第一个多属性图片保存为主sku图片
                        if (!$productImgIds = $itemImageModel->batchSaveData($imageSaveData, $list_id, $this->_imgRelateTab['product'])) {
                            throw new Exception('sku: ' . $sku . '，图片信息保存失败！');
                        }
                        # 保存成刊登才需要生成刊登任务
                        if ($upload_type == 2) {
                            $parentXmlData = $data['Parent'];
                            $childXmlData = $data['Child'];
                            foreach ($parentXmlData as $k => $v) {
                                if (isset($variantData['Parent'][$k]) && $k != 'VariationData') {
                                    unset($parentXmlData[$k]);
                                }
                            }
                            foreach ($childXmlData as $k => $v) {
                                if (isset($variantData['Child'][$k]) && $k != 'VariationData') {
                                    unset($childXmlData[$k]);
                                }
                            }
                            $xmlData = [
                                'Product' => $data['Product'],
                                'Parent' => $parentXmlData,
                                'Child' => $childXmlData,
                            ];
                            $parentageVal = 'parent';
                            $xmlData['VariantData']['ProductImage'] = $variantData['ProductImage'];
                            $ids = [
                                'productId' => $list_id,
                                'imageId' => $productImgIds,
                            ];
                            $fileData = $this->getGenerateXmlData($xmlData, [$cateNameTree_p, $cateNameTree_cd], '',
                                $currency, $parent_platform_sku, $nodeTrees, $childCateType, $parentageVal);
                            # 将文件名保存，出现异常就删除
                            $allFileData[] = $fileData;
                            # 添加刊登任务
                            $this->saveKdTaskData($fileData, $ids, $saveAccount, $parent_sku, $parent_platform_sku);
                        }
                    }

                    # 保存多属性图片
                    if (!$variantImgIds = $itemImageModel->batchSaveData($imageSaveData, $variantId, $this->_imgRelateTab['variant'])) {
                        throw new Exception('sku: ' . $sku . '，图片信息保存失败！');
                    }
                    # 保存成刊登才需要生成刊登任务
                    if ($upload_type == 2) {
                        # 生成xml文件体
                        $parentCateData = array_merge_recursive($data['Parent'] ?: [], $variantData['Parent'] ?: []);
                        $childCateData = array_merge_recursive($data['Child'] ?: [], $variantData['Child'] ?: []);
                        $xmlData = [
                            'Product' => $data['Product'],
                            'Parent' => $parentCateData,
                            'Child' => $childCateData,
                            'VariantData' => $variantData,
                            'SaleData' => $saleData,
                        ];
                        $parentageVal = $goods_type == 1 ? 'child' : null;
                        $fileData = $this->getGenerateXmlData($xmlData, [$cateNameTree_p, $cateNameTree_cd], $upc,
                            $currency, $platform_sku, $nodeTrees, $childCateType, $parentageVal);
                        # 将文件名保存，出现异常就删除
                        $allFileData[] = $fileData;
                        # 关联ID数组
                        $ids = [
                            'productId' => $variantId,
                            'imageId' => $variantImgIds,
                        ];
                        # 添加刊登任务
                        $this->saveKdTaskData($fileData, $ids, $saveAccount, $sku, $platform_sku);
                    }
                }
                $itemModel->commit();
                $this->ajaxReturn(['code' => 1]);
            }
        } catch (Exception $e) {
            $itemModel->rollback();
            $upcModel->setUpcNum(2);
            $this->delFile($allFileData);
            $returnData = array(
                'code' => 0,
                'msg' => $e->getMessage(),
            );
            $this->ajaxReturn($returnData);
        }
    }

    public function getVariantThemeData($data)
    {
        switch (true) {
            case $data['Child']['VariationData']['VariationTheme']:
                return [$data['Child']['VariationData']['VariationTheme'], 'Child,VariationData,VariationTheme'];
            case $data['Child']['VariationTheme']:
                return [$data['Child']['VariationTheme'], 'Child,VariationData'];
            case $data['Parent']['VariationData']['VariationTheme']:
                return [$data['Parent']['VariationData']['VariationTheme'], 'Parent,VariationData,VariationTheme'];
            case $data['Parent']['VariationTheme']:
                return [$data['Parent']['VariationTheme'], 'Parent,VariationData'];
            default:
                return ['', ''];
        }

    }

    # 编辑单个产品信息
    public function editVariantItemSave()
    {
//                file_put_contents('C:\Users\Administrator.USER-20170118OD\Desktop\tmpfile/ccc1.php', '<?php '.PHP_EOL.'return ' . var_export($_POST, true) . ';');
//                die;
//        $_POST = include 'C:\Users\Administrator.USER-20170118OD\Desktop\tmpfile/ccc1.php';
        import('@.Service.WalmartSkuOperateService');
        $categoryModel = new AmazonCategoryModel();
        $itemModel = new AmazonItemModel();
        $itemAttrInfoModel = new AmazonItemAttrInfoModel();
        $itemImageModel = new AmazonItemImageModel();
        $itemBulletpointSearchtermsModel = new AmazonItemBulletpointSearchtermsModel();
        $upcModel = new UpcModel();
        $variantItemModel = D('AmazonVariantItem');

        $upload_type                 = $_POST['uploadType'];
        $cateid                      = $_POST['cateid'];
        $cateTree                    = $categoryModel->getCategoryTreeByChildId($cateid);
        $cateNameTree_p              = $cateTree['parentCateName'];
        $cateNameTree_cd             = $cateTree['categoryName'];
        $editFlag = $_POST['editFlag'];
        $list_id = $_POST['listId'];
        $variantId = $_POST['variantId'];
        # 获取分类属性节点树
        $nodeTrees = $this->getCategoryNodeTree($cateid, $cateNameTree_cd);
        if (! empty($cateNameTree_cd)) {
            $childCateType = $categoryModel->where(['pid' => 0, 'name' => $cateNameTree_p])->getField('relate_type');
        }
        $goods_type                  = $_POST['goods_type'];
        $parent_sku                  = $_POST['sku'];
        $allFileData = array();
        $itemModel->startTrans();
        try {
            $where = array(
                'id' => $list_id,
            );
            $info = $itemModel->where($where)->find();
            $parent_platform_sku = $info['platform_sku'];
            $accountList = [str_replace(' ', '_', $info['account'])];
            $this->clearVariantSaveDataByPlatformSku($list_id);

            foreach ($accountList as $account) {
                $data = $_POST[$account];
                $saveAccount = str_replace('_', ' ', $account);
                # 获取币种
                $currency = $this->_accountList[$account]['currency'];
//                $variantGroupId = $variant_group_id_input ? : $walmartSkuOperateService->walmartSkuEncode($parent_sku);
                # 保存数据存储数组
                $itemSaveData = [];
                $bullPointSaveData = [];

                # 描述类信息
                $product = $data['Product'];
                $itemSaveData['title'] = $product['DescriptionData']['Title'];
                $itemSaveData['description'] = $product['DescriptionData']['Description'];
                $itemSaveData['brand'] = $product['DescriptionData']['Brand'];
                $itemSaveData['condition'] = $product['Condition'];
                $itemSaveData['recommended_browse_node'] = $product['DescriptionData']['RecommendedBrowseNode'];
                # 保存item信息
                $itemSaveData['status'] = 1;
                if ($editFlag == 1 || $goods_type == 0) {
                    $itemSaveData['product_status'] = 1;
                }
                $itemSaveData['updated_at'] = time();
                $itemSaveData['id'] = $list_id;
                if (! $itemModel->save($itemSaveData)) {
                    throw new Exception('基本信息保存失败! ');
                }

                foreach ($product['DescriptionData']['BulletPoint'] as $k => $bulletPoint) {
                    if ($k == 5) {
                        break;
                    }
                    $bullPointSaveData[] = ['type' => 1, 'info' => $bulletPoint];
                }
                foreach ($product['DescriptionData']['SearchTerms'] as $k => $searchTerms) {
                    if ($k == 5) {
                        break;
                    }
                    $bullPointSaveData[] = ['type' => 2, 'info' => $searchTerms];
                }
                # 亮点跟描述信息保存
                $bullPointRes = $itemBulletpointSearchtermsModel->batchSaveData($bullPointSaveData, $list_id);
                if (!$bullPointRes) {
                    throw new Exception('亮点信息保存失败！');
                }
                # 处理前台传入的属性数据
                $attrInfoSaveData = $this->dealAttrInfoData($data['Parent'], $data['Child'], $nodeTrees[1], $nodeTrees[0]);
                if (!empty($attrInfoSaveData)) {
                    if (!$itemAttrInfoModel->batchSaveData($attrInfoSaveData, $list_id)) {
                        throw new Exception('属性信息保存失败！');
                    }
                }

                # 多属性上传先传主sku
                if ($goods_type == 1 && $editFlag == 1) {
                    $themeTree = explode(',', $info['variation_theme_tree']);
                    $nodeSite = $themeTree[0];
                    unset($themeTree[0]);
                    $themeData = $this->mkmultiarr($themeTree, $info['variation_theme']);
                    if ($nodeSite == 'Parent') {
                        $data['Parent'] = array_merge_recursive($data['Parent'], $themeData);
                    } else {
                        $data['Child'] = array_merge_recursive($data['Child'], $themeData);
                    }
                    $xmlData = [
                        'Product' => $data['Product'],
                        'Parent' => $data['Parent'],
                        'Child' => $data['Child'],
                    ];
                    $parentageVal = 'parent';
                    $ids = [
                        'productId' => $list_id,
                    ];

                    $parentXmlData = $data['Parent'];
                    $childXmlData = $data['Child'];
                    foreach ($parentXmlData as $k => $v) {
                        if (isset($variantData['Product'][$k]) && $k != 'VariationData') {
                            unset($parentXmlData[$k]);
                        }
                    }
                    foreach ($childXmlData as $k => $v) {
                        if (isset($variantData['Child'][$k]) && $k != 'VariationData') {
                            unset($childXmlData[$k]);
                        }
                    }

                    $fileData = $this->getGenerateXmlData($xmlData, [$cateNameTree_p, $cateNameTree_cd], '',
                        $currency, $parent_platform_sku, $nodeTrees, $childCateType, $parentageVal, true);
                    # 将文件名保存，出现异常就删除
                    $allFileData[] = $fileData;
                    # 添加刊登任务
                    $this->saveKdTaskData($fileData, $ids, $saveAccount, $parent_sku, $parent_platform_sku);
                }

                if ($editFlag == 2) {
                    # 多属性信息处理
                    $skus = $data['sku'];
                    foreach ($skus as $index => $sku) {
                        $variantData = $data['sku-' . $sku];
                        # 保存成不刊登则不需要获取upc
                        $upc = $upcModel->getSingleUpc(2);
                        if ($upc === false) {
                            throw new Exception('获取upc失败! ');
                        }
                        # 多属性信息
                        $variantSaveData = [
                            'upc' => $upc,
                            'relation_status' => $goods_type == 1 ? 0 : 4,
                            'status' => 1,
                            'variant_status' => 1,
                            'updated_at' => time(),
                        ];
                        # 处理前台传入的多属性数据
                        if ($goods_type == 1) {
                            $variantAttrInfo = $this->dealAttrInfoData($variantData['Parent'], $variantData['Child'], $nodeTrees[1], $nodeTrees[0]);
                            $variantSaveData['variant_info'] = json_encode($variantAttrInfo);
                        }
                        $variantSaveData['id'] = $variantId;
                        if (!$variantItemModel->save($variantSaveData)) {
                            throw new Exception('sku: ' . $sku . '，多属性信息保存失败！');
                        }

                        $variantInfo = $variantItemModel->where('id="' . $variantId . '"')->find();

                        # 生成xml文件体
                        $parentCateData = array_merge_recursive($data['Parent'] ?: [], $variantData['Parent'] ?: []);
                        $childCateData = array_merge_recursive($data['Child'] ?: [], $variantData['Child'] ?: []);
                        $xmlData = [
                            'Product' => $data['Product'],
                            'Parent' => $parentCateData,
                            'Child' => $childCateData,
                            'VariantData' => $variantData,
                        ];
                        $parentageVal = $goods_type == 1 ? 'child' : null;
                        $fileData = $this->getGenerateXmlData($xmlData, [$cateNameTree_p, $cateNameTree_cd], $upc,
                            $currency, $variantInfo['platform_sku'], $nodeTrees, $childCateType, $parentageVal, true);
                        # 将文件名保存，出现异常就删除
                        $allFileData[] = $fileData;
                        # 关联ID数组
                        $ids = [
                            'productId' => $variantId,
                        ];
                        # 添加刊登任务
                        $this->saveKdTaskData($fileData, $ids, $saveAccount, $sku, $variantInfo['platform_sku']);
                    }
                }
                $itemModel->setKdStatus($list_id);
                $itemModel->commit();
                $this->ajaxReturn(['code' => 1]);
            }
        } catch (Exception $e) {
            $itemModel->rollback();
            $upcModel->setUpcNum(2);
            $this->delFile($allFileData);
            $returnData = array(
                'code' => 0,
                'msg' => $e->getMessage(),
            );
            $this->ajaxReturn($returnData);
        }
    }

    function mkmultiarr($arr, $value) {
        $result = $value;
        for($i = count($arr); $i >= 1; $i--) {
            $result = array($arr[$i]=>$result);
        }
        return $result;
    }


    /**
     * 添加刊登任务
     * @param $fileData 文件名数组
     * @param $account 账户
     * @param $sku sku
     */
    private function saveKdTaskData($data, $ids, $account, $sku, $platform_sku)
    {
        $taskModel = new AmazonSubmitFeedTaskModel();
        foreach ($data as $resource => $fileData) {
            $status = 0;
            switch(strtolower($resource)) {
                case 'product':
                    $status = 1;
                case 'inventory':
                case 'price':
                    $fileName = current($fileData);
                    $messageId = key($fileData);
                    $relateId = $ids['productId'];
                    if (!$taskModel->saveKdTaskData($fileName, $resource, $relateId, $account, $sku, $platform_sku, $messageId, $status)) {
                        throw new Exception('保存' + $resource + '任务失败！');
                    }
                case 'image':
                    foreach ($fileData as $k => $imgFileData) {
                        if ($k === 'main' || $k === 'swatch') {
                            $fileName = current($imgFileData);
                            $messageId = key($imgFileData);
                            $relateId = $ids['imageId'][$k];
                            if (!$taskModel->saveKdTaskData($fileName, $resource, $relateId, $account, $sku, $platform_sku, $messageId, $status)) {
                                throw new Exception('保存' + $k + 'image任务失败！');
                            }
                        } else {
                            foreach ($imgFileData as $key => $ptImgInfo) {
                                $fileName = current($ptImgInfo);
                                $messageId = key($ptImgInfo);
                                $relateId = $ids['imageId'][$k][$key];
                                if (!$taskModel->saveKdTaskData($fileName, $resource, $relateId, $account, $sku, $platform_sku, $messageId, $status)) {
                                    throw new Exception('保存' + $k + 'image任务失败！');
                                }
                            }
                        }
                    }
            }
        }
    }

    /**
     * 创建生成xml文件的数组
     * @param $xmlData 具体数据信息
     * @param $cateNames 分类树
     * @param $upc upc
     * @param $currency 币种
     * @param $platform_sku 刊登sku
     * @param $nodeTrees 节点树
     * @param $childCateType 子类类型 有ref与枚举两种类型
     * @param null $parentageVal parentage节点值，标识父子sku
     * @return array
     */
    private function getGenerateXmlData($xmlData, $cateNames, $upc, $currency, $platform_sku, $nodeTrees, $childCateType, $parentageVal = null, $isVariantEdit = false)
    {
        $generateXmlService = new AmazonGenerateXmlService();
        # 产品信息存文件
        $productData = $this->getGenerateProductXmlData($xmlData, $cateNames, $upc, $platform_sku, $nodeTrees, $childCateType, $parentageVal);
        $res['product'] = $generateXmlService->generateXmlBody($productData, 'product');
        if (! $isVariantEdit) {
            # 图片信息存文件
            $imagedData = $this->getGenerateImageXmlData($xmlData['VariantData']['ProductImage'], $platform_sku);
            $res['image'] = $this->generateImageXmlFile($imagedData);
            if (isset($xmlData['VariantData']['Inventory']) && isset($xmlData['VariantData']['Price'])) {
                # 价格信息存文件
                $priceData = [
                    'standardPrice' => $xmlData['VariantData']['Price']['StandardPrice'],
                    'salePrice' => $xmlData['VariantData']['Price']['SalePrice'],
                    'startDate' => $xmlData['SaleData']['StartDate'],
                    'endDate' => $xmlData['SaleData']['EndDate'],
                    'sku' => $platform_sku,
                    'currency' => $currency,
                ];
                $res['price'] = $generateXmlService->generateXmlBody($priceData, 'price');
                # 库存信息存文件
                $inventory = $xmlData['VariantData']['Inventory']['FulfillmentCenterID']['Quantity'];
                $inventoryData = ['inventory' => $inventory, 'sku' => $platform_sku];
                $res['inventory'] = $generateXmlService->generateXmlBody($inventoryData, 'inventory');
            }
        }
        return $res;
    }

    private function getGenerateProductXmlData($xmlData, $cateNames, $upc, $platform_sku, $nodeTrees, $childCateType, $parentageVal = null)
    {
        # 占位用
        if (! empty($cateNames[1]) && $childCateType) {
            $xmlData['Parent']['ProductType'][$cateNames[1]] = 1;
        }
        # 基本信息排序
        $rebuildData = $this->productDataOrder($xmlData['Product'], $upc, $platform_sku);
        # 父类元素节点排序去重
        $rebuildData['ProductData'][$cateNames[0]] = $this->arrSortAndDelEmpry($xmlData['Parent'], $nodeTrees[1], $parentageVal) ? : '';
        if (! empty($cateNames[1]) && $childCateType) {   //存在子类节点信息
            if ($childCateType == 1) {  //ref类型
                $rebuildData['ProductData'][$cateNames[0]]['ProductType'] = [];
                # 子类元素节点排序去重
                $rebuildData['ProductData'][$cateNames[0]]['ProductType'][$cateNames[1]] = $this->arrSortAndDelEmpry($xmlData['Child'], $nodeTrees[0], $parentageVal) ? : '';
            } else {    // 枚举类型
                $rebuildData['ProductData'][$cateNames[0]]['ProductType'] = $cateNames[1];
            }
        }
        $data['Product'] = $rebuildData;
//        $generateXmlService = new AmazonGenerateXmlService();
//        $xml = $generateXmlService->buildXml($data);
        return $data;
    }

    # 库存数组
    private function getGenerateInventoryXmlData($data, $platform_sku)
    {
        $inventoryData['Inventory']['SKU'] = $platform_sku;
        $inventoryData['Inventory']['FulfillmentCenterID']['Quantity'] = $data['FulfillmentCenterID']['Quantity'];
        return $inventoryData;
    }

    # 价格数组
    private function getGeneratePriceXmlData($data, $platform_sku)
    {
        $priceData['Price']['SKU'] = $platform_sku;
        $priceData['Price']['StandardPrice'] = $data['StandardPrice'];
        return $priceData;
    }

    # 图片数组
    private function getGenerateImageXmlData($data, $platform_sku)
    {
        # 主图
        $imagesData['main'] = $this->getGenerateSingleImageXmlData($data['Main'], 'Main', $platform_sku);
        # 多属性标识图
        if (isset($data['Swatch'])) {
            $imagesData['swatch'] = $this->getGenerateSingleImageXmlData($data['Swatch'], 'Swatch', $platform_sku);
        }
        # 附图
        if (isset($data['PT'])) {
            foreach ($data['PT'] as $k => $url) {
                if ($k >= 8) {
                    break;
                }
                $imagesData['PT'][] = $this->getGenerateSingleImageXmlData($url, 'PT' . ($k + 1), $platform_sku);
            }
        }
        return $imagesData;
    }

    private function generateImageXmlFile($imagedData)
    {
        $generateXmlService = new AmazonGenerateXmlService();
        foreach ($imagedData as $imgType => $imgInfo) {
            if ($imgType === 'main') {
                $res['main'] = $generateXmlService->generateXmlBody($imgInfo, 'image');
            } else if ($imgType === 'swatch') {
                $res['swatch'] = $generateXmlService->generateXmlBody($imgInfo, 'image');
            } else {
                foreach ($imgInfo as $key => $ptInfo) {
                    $res['PT'][] = $generateXmlService->generateXmlBody($ptInfo, 'image');
                }
            }
        }
        return $res;
    }

    # 单个图片
    private function getGenerateSingleImageXmlData($url, $imageType, $sku)
    {
        $imageData = array();
        $imageData['ProductImage']['SKU'] = $sku;
        $imageData['ProductImage']['ImageType'] = $imageType;
        $imageData['ProductImage']['ImageLocation'] = $url;
        return $imageData;
    }

    /**
     * 基本信息排序
     */
    private function productDataOrder($data, $upc, $platform_sku)
    {
        $orderData = array();
        $orderData['SKU'] = $platform_sku;
        if (! empty($upc)) {
            $orderData['StandardProductID']['Type'] = 'UPC';
            $orderData['StandardProductID']['Value'] = $upc;
        }
        $orderData['DescriptionData']['Title'] = $this->dealSpecialXmlInfo($data['DescriptionData']['Title']);
        $orderData['DescriptionData']['Brand'] = $data['DescriptionData']['Brand'];
        $orderData['DescriptionData']['Description'] = $this->dealSpecialXmlInfo($data['DescriptionData']['Description']);
        $orderData['DescriptionData']['BulletPoint'] = [];
        foreach ($data['DescriptionData']['BulletPoint'] as $k => $bulletPoint) {
            if ($k == 5) {
                break;
            }
            $orderData['DescriptionData']['BulletPoint'][] = $this->dealSpecialXmlInfo($bulletPoint);
        }
        $orderData['DescriptionData']['Manufacturer'] = $data['DescriptionData']['Brand'];
        if (! empty($upc)) {
            $orderData['DescriptionData']['MfrPartNumber'] = str_replace(' ', '-', $data['DescriptionData']['Brand']) . (string)time() . mt_rand(100000, 999999);
        }
        $orderData['DescriptionData']['SearchTerms'] = [];
        foreach ($data['DescriptionData']['SearchTerms'] as $k => $searchTerms) {
            if ($k == 5) {
                break;
            }
            $orderData['DescriptionData']['SearchTerms'][] = $this->dealSpecialXmlInfo($searchTerms);
        }
        $orderData['DescriptionData']['RecommendedBrowseNode'] = $data['DescriptionData']['RecommendedBrowseNode'];
        return $orderData;
    }

    // 处理含有特殊字符的信息
    private function dealSpecialXmlInfo($info)
    {
        return '<![CDATA[' . $info . ']]>';
    }

    /**
     * 在事务回滚时删除xml文件
     */
    private function delFile($fileData)
    {
        foreach ($fileData as $v) {
            if (is_file($v)) {
                unlink($v);
            } else {
                $this->delFile($fileData);
            }
        }
    }

    /**
     * 处理Parentage节点数据信息
     * @param $data 分类数据
     * @param $nodeData  分类节点数据
     * @param $val Parentage节点值
     * @return array
     */
    private function dealParentageNodeData($data, $nodeData, $val)
    {
        if (isset($nodeData['VariationData']['Parentage'])) {
            $data['VariationData']['Parentage'] = $val;
        } else if (isset($nodeData['Parentage'])) {
            $data['Parentage'] = $val;
        }
        return $data;
    }


    /**
     * 处理前台传入的属性数据
     * @param null $parentData  父分类属性数据
     * @param null $childData   子分类属性数据
     * @return array
     */
    public function dealAttrInfoData($parentData = null, $childData = null, $parentCategoryNodeTree, $chilCategoryNodeTree)
    {
        $parentAttrSaveData = [];
        $childAttrSaveData = [];
        # parent属性处理
        if (! empty($parentData)) {
            # 先排序去重
            $parentSortData = $this->arrSortAndDelEmpry($parentData, $parentCategoryNodeTree);
            # 创建父类属性保存数据
            $parentAttrSaveData = empty($parentData) ? [] : $this->createAttrInfoSaveData(['Parent' => $parentSortData], 'reset');
        }

        # child属性处理
        if (! empty($childData)) {
            # 先排序去重
            $childSortData = $this->arrSortAndDelEmpry($childData, $chilCategoryNodeTree);
            # 创建父类属性保存数据
            $childAttrSaveData = empty($childData) ? [] : $this->createAttrInfoSaveData(['Child' => $childSortData], 'reset');
        }
        return array_merge($parentAttrSaveData, $childAttrSaveData);
    }

    /**
     * 多维数组的排序去空值
     * @param $disorderDatas  需要排序的数组
     * @param $sortDataArr    有序数组
     * @return array
     */
    public function arrSortAndDelEmpry($disorderDatas, $sortDataArr, $parentageVal = null)
    {
        if (! empty($parentageVal)) {
            $disorderDatas = $this->dealParentageNodeData($disorderDatas, $sortDataArr, $parentageVal);
        }
        $disorderDatas = $disorderDatas;
        $orderDatas = array();
        foreach($sortDataArr as $key => $sortData) {
            if(! isset($disorderDatas[$key])) {
                continue;
            }
            if(is_array($sortData)) {
                $orderData = $this->arrSortAndDelEmpry($disorderDatas[$key], $sortData);
            } else {
                $orderData = $disorderDatas[$key];
            }
            // 若信息为空，则不作处理，此条记录将被删除
            if(empty($orderData)) {
                if (isset($disorderDatas[$key . '_ph'])) {
                    $orderData = '';
                } else {
                    continue;
                }
            }
            $orderDatas[$key] = $orderData;
            // 若存在属性信息，属性信息保留
            if (isset($disorderDatas[$key . '_attribute'])) {
                $orderDatas[$key . '_attribute'] = $disorderDatas[$key . '_attribute'];
            }
        }
        return $orderDatas;
    }

    /**
     * 获取分类属性节点树
     * @param $cateid   分类id
     * @param $cateNameTree_cd  子分类名
     * @return array
     */
    public function getCategoryNodeTree($cateid, $cateNameTree_cd)
    {
        $generateHtmlService = new AmazonGenerateHtmlService($cateid);
        $categoryNodeTree = $generateHtmlService->getCategoryNodeTree();
        $parentCategoryNodeTree = current($categoryNodeTree);
        if (empty($cateNameTree_cd)) {
            $chilCategoryNodeTree = null;
        } else {
            $chilCategoryNodeTree = current(current($categoryNodeTree)['ProductType']);
//            unset($parentCategoryNodeTree['ProductType'][$cateNameTree_cd]);
            // 把ProductType具体节点信息去掉，换成占位符
            isset($parentCategoryNodeTree['ProductType']) && $parentCategoryNodeTree['ProductType'] = 1;
        }
        return [$chilCategoryNodeTree, $parentCategoryNodeTree];
    }

    /**
     * 创建sys_amaozn_item_attr_info表存储数据
     * @param $data  存储数据
     * @param null $status  是否重置
     * @param array $attr_tree  多维数组下标连接树，用，分隔
     * @param string $attr_name  当前下标名
     * @return array
     */
    public function createAttrInfoSaveData($data, $status = null, $attr_tree = array(), $attr_name = '')
    {
        static $saveData = array();
        // 由于静态变量只能定义一次，此函数需被多次调用，每次调用做一次重置
        if($status == 'reset') {
            $saveData = array();
        }
        foreach ($data as $k => $v) {
            $tmp_attr_tree = $attr_tree;
            $tmp_attr_tree[] = $k;
            if ($k === 'Child' || $k === 'Parent') {
                $tmp_attr_name = $attr_name ? : '';
            } else {
                $tmp_attr_name = $attr_name ?: $k;
            }
            if(is_array($v)) {
                $this->createAttrInfoSaveData($v, null, $tmp_attr_tree, $tmp_attr_name);
            } else {
                $saveData[] = array(
                    'attr_name' => $tmp_attr_name,
                    'attr_value' => $v,
                    'attr_tree' => implode(',', $tmp_attr_tree),
                );
            }
        }
        return $saveData;
    }

    # 编辑产品之前先删除产品信息
    public function clearProductSaveDataByPlatformSku($listId, $type)
    {
        $itemModel = new AmazonItemModel();
        $itemAttrInfoModel = new AmazonItemAttrInfoModel();
        $itemImageModel = new AmazonItemImageModel();
        $variantItemModel = new AmazonVariantItemModel();
        $itemBulletpointSearchtermsModel = new AmazonItemBulletpointSearchtermsModel();

        # 获取此产品下的所有变体id
        $variantIds = $variantItemModel->where('list_id="' . $listId . '"')->getField('id', true);
        # 删除图片信息
        $imageFlag = $itemImageModel->deleteProductImage($listId, $variantIds, $type);
        # 删除亮点信息
        $bulletpointFlag = $itemBulletpointSearchtermsModel->where('list_id="' . $listId . '"')->delete();
        # 删除属性信息
        $attrInfoFlag = $itemAttrInfoModel->where('list_id="' . $listId . '"')->delete();
        # 删除变体信息
        $map = array(
            'id' => ['in', implode(',', $variantIds)],
        );
        $variantFlag = $variantItemModel->where($map)->delete();
        # 删除产品信息
        $productFlag = $itemModel->where('id="' . $listId . '"')->delete();
        if ( ! ($imageFlag && $bulletpointFlag && $attrInfoFlag && $variantFlag && $productFlag)) {
            throw new Exception('删除信息失败！');
        }
    }

    # 编辑变体之前先删除部分信息
    public function clearVariantSaveDataByPlatformSku($listId)
    {
        $itemAttrInfoModel = new AmazonItemAttrInfoModel();
        $itemBulletpointSearchtermsModel = new AmazonItemBulletpointSearchtermsModel();
        # 删除亮点信息
        $bulletpointFlag = $itemBulletpointSearchtermsModel->where('list_id="' . $listId . '"')->delete();
        # 删除属性信息
        $attrInfoFlag = $itemAttrInfoModel->where('list_id="' . $listId . '"')->delete();
        if ( ! ($bulletpointFlag && $attrInfoFlag)) {
            throw new Exception('删除信息失败！');
        }
    }

    /**
     * 编辑页面获取二级分类列表
     */
    public function getCategories($categoryParentID) {
        $categoryModel = new AmazonCategoryModel();
        echo $categoryModel->getCategory($categoryParentID);
    }

    /**
     * ajax根据关键字获取类目
     */
    public function getCategoryTableByKeyword() {
        if (IS_POST) {
            $keyWord = trim($_POST['keyword']);
            $categoryModel = new AmazonCategoryModel();
            $categoryResult = $categoryModel->recommendCategoryByKeyword($keyWord);
            if (count($categoryResult) > 0) {
                $returnStr = '<table id="rounded-corner" style="width:100% !important;margin:0 !important;" summary="2007 Major IT Companies\' Profit"><thead><tr><th id="vzebra-comedy" scope="col"> </th><th id="vzebra-action" scope="col">一级分类名称</th><th id="vzebra-action" scope="col">二级分类名称</th></tr></thead><tbody>';
                foreach ($categoryResult as $categoryData) {
                    $categoryId = $categoryData['category']['cate_id'] ? : $categoryData['parentCategory']['cate_id'];
                    $categoryName = $categoryData['category']['name'] ? : $categoryData['parentCategory']['name'];
                    $returnStr .= '<tr><td><input type="checkbox" onclick="closeLayer(this)" value="' . $categoryId . '" categoryName = "' . $categoryName . '"/></td><td>' . $categoryData['parentCategory']['name'] . '</td><td>' . $categoryData['category']['name'] . '</td></tr>';
                }
                $returnStr .= '</tbody></table>';
            } else {
                $returnStr = "没有匹配到分类!";
            }
            echo $returnStr;
            die();
        }
    }

    # 搜索
    public function searchRecommendedBrowseNode()
    {
        $keyWord = $_POST['keyword'];
        $site = $_POST['site'];
        if ($site === 'us') {
            $this->searchUsRecommendedBrowseNode($keyWord);
        } else {
            $this->searchEurRecommendedBrowseNode($keyWord);
        }
    }

    # 欧洲站点
    public function searchEurRecommendedBrowseNode($keyWord)
    {
        $recommendedBrowseNodeModel = new AmazonRecommendedBrowseNodeListModel();
        $where = [
            'node_path' => ['like', "%{$keyWord}%"],
        ];
        $data = $recommendedBrowseNodeModel->where($where)->select();
        $str = '';
        foreach($data as $v) {
            $str .= '<li';
            foreach ($v as $k1 => $v1) {
                if ($k1 === 'id' || $k1 === 'node_path' || $k1 === 'us') {
                    continue;
                }
                $str .= " {$k1}='{$v1}' ";
            }
            $str .= '><span>' . $v['node_path'] . '</span></li>';
        }
        echo $str;
    }

    # 美国站点
    public function searchUsRecommendedBrowseNode($keyWord)
    {
        $usRecommendedBrowseNodeModel = new AmazonUsRecommendedBrowseNodeListModel();
        $where = [
            'node_path' => ['like', "%{$keyWord}%"],
        ];
        $data = $usRecommendedBrowseNodeModel->where($where)->select();
        $str = '';
        foreach($data as $v) {
            $str .= '<li us="' . $v['item_type_keyword'] . '"><span>' . $v['node_path'] . '</span></li>';
        }
        echo $str;
    }

    # 导入upc
    public function addUPC()
    {
        if (IS_GET) {
            $from = $_GET['from'];
            $this->assign('from', $from);
            $this->display();
        } else {
            $from = $_POST['from'] ? : 1;
            $upcs = $_POST['upcs'];
            $matchs = [];
            preg_match_all('/\d{12}/m', $upcs, $matchs);
            $upcs = array_unique($matchs[0]);
            if (empty($upcs)) {
                $returnData = [
                    'code' => 0,
                    'msg' => '所传入的所有upc均不是12位数字',
                ];
                $this->ajaxReturn($returnData);
            }
            $upcMode = new UpcModel();
            $saveData = [];
            foreach ($upcs as $upc) {
                $count = $upcMode->where('upc="' . $upc . '"')->count();
                if ($count) {
                    continue;
                }
                $saveData[] = [
                    'from' => $from,
                    'upc' => $upc,
                ];
            }
            if (empty($saveData)) {
                $returnData = [
                    'code' => 0,
                    'msg' => '所传入的所有upc之前所导入的upc重复重复',
                ];
                $this->ajaxReturn($returnData);
            }
            if ($upcMode->addAll($saveData)) {
                $upcNum = $upcMode->setUpcNum($from);
                $returnData = [
                    'code' => 1,
                    'upcNum' => $upcNum,
                ];
            } else {
                echo $upcMode->getLastSql();
                $returnData = [
                    'code' => 0,
                    'msg' => 'upc保存失败',
                ];
            }
            $this->ajaxReturn($returnData);
        }
    }

    /**
     * 获取主图备选图片
     */
    public function getMainPicture() {
        $sku = $_REQUEST['sku'];
        $picimg = $this->getWaitingPictures($sku);
        $this->assign('pics', $picimg);
        $this->display();
    }

    /**
     * 获取副图备选图片
     */
    public function getExtraPictures() {
        $sku = $_REQUEST['sku'];
        $mulattribute = $_REQUEST['mulattribute'];
        $picimg = array();
        if ($sku != '' && $sku != 'account') {
            $account = $_REQUEST['account'];
            $picimg = $this->getWaitingPictures($sku);
        }
        //搜索SKU图片
        if(isset($_GET['Select'])&& $_GET['Select'] !='null'){
            $skuArray = explode(',',$_GET['Select']);
            foreach($skuArray as $item){
                $picsku = $this->getWaitingPictures($item);
                $picimg = array_merge($picimg,$picsku);
            }
        }

        //多属性SKU
        $attributeSku  = $this->getProdcutSkuErp($sku);
        $attributeSku[]['goods_sn'] = $sku;

        $this->assign('attributeSku', $attributeSku);
        $this->assign('sku', $sku);
        $this->assign('mulattribute', $mulattribute);
        $this->assign('pics', $picimg);
        $this->assign('account', $account);
        $this->display();
    }

    //获取erp里采集的多属性sku
    public function getProdcutSkuErp($sku=false){
        $db=M('ebay_goods',NULL,'DB_CONFIG2');
        if($sku){
            $data['BtoBnumber'] = $sku;
        }

        $result  = $db->field("goods_sn")->where($data)->select();
        return $result;
    }

    /**
     * 获取备选图片
     */
    public function getWaitingPictures($sku) {
        $picimg = array();
        if ($sku != '') {
            import('@.Service.JoomImgUploadService');
            $joomImgUploadService = new JoomImgUploadService();
            $picimg = $joomImgUploadService->ebayGetPic($sku, 30);
            $picimg = $picimg['data'];
        }
        foreach($picimg as $k => $v) {
            $picimg[$k]->pic = $v->path;
        }
        return $picimg;
    }

    # 单个url地址请求香港服务器
    public function imageUrlUpload()
    {
        $picUrl = $_POST['picUrl'];
        import('@.Service.JoomImgUploadService');
        $joomImgUploadService = new JoomImgUploadService();
        $res = $joomImgUploadService->imgServicePicToCdn($picUrl);
        if(isset($res['Success'])){
            $resData['path']=$res['Url'];
            $resData['code']=0;
        }else{
            $resData['msg']=$res['Error'];
            $resData['code']=1;
        }
        $this->ajaxReturn($resData);
    }

    # 上传数据xml
    public function buildUploadItemXml($data){
        $xml = '';
        foreach($data as $v) {
            $xml .= $this->arrayToXml1($v);
        }
        return $xml;
    }


}