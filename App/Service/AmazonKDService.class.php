<?php


class AmazonKDService
{
    public static function getAccountListOptions($sellerArray, $account = '') {
        $return = '';
        foreach($sellerArray as $val){
            $return .= '<div class="col col-2"><div class="block-group">';
            $return .= '<label class="mdn-option">';
            $return .= '<input style="zoom:60%" class="accountCheckbox" type="checkbox" name="accountList[]" value="'.$val.'" ';
            if ($val == $account) {
                $return .= ' checked';
            }
            $return .= ' />';
            $return .= '<span class="mdn-checkbox"></span>';
            $return .= '<span style="font-size: 12px" class="option-label">'.$val.'</span>';
            $return .= '</label>';
            $return .= '</div></div>';
        }
        return $return;
    }

    public function getProducTitle($sku)
    {
        $dbTitle = new ErpListTitleModel();
        $condition['sku'] = $sku;
        return $dbTitle->where($condition)->getField('title', true);
    }

    public function getProductDescription($sku)
    {
        $dbDescr = new EbayDescriptionModel();
        $condition['sku'] = $sku;
        $descriptions = $dbDescr->where($condition)->field('shortDescription,textDescription')->find();
        $bulletPoints = explode(PHP_EOL, $descriptions['textDescription']);
        $num = 0;
        foreach($bulletPoints as $bulletPoint) {
            if(preg_match('/\w+/', $bulletPoint)) {
                $descriptions['bulletPoint'][] = str_replace(array('"', "'"), '', $bulletPoint);
                $num++;
            }
            if($num == 5) {
                break;
            }
        }
        return $descriptions;
    }

    /**
     * 将纯文本转为富文本
     */
    public function textToRichtext($text){
        $textArr = explode(PHP_EOL,$text);
        $str='';
        foreach ($textArr as $item) {
            $str.='<p style="margin:5px auto"><span style="font-family:&#39;Times New Roman&#39;;font-size:16px">'.$item.'</span></p>';
        }
        return $str;
    }

    public function uploadFile()
    {
        vendor("PHPExcel.PHPExcel");
        $dir = 'C:\Users\Administrator.USER-20170118OD\Desktop\am\file/';
        $handler = opendir($dir);
        while (($filePath = readdir($handler)) !== false) {
            if ($filePath === '.' || $filePath === '..') {
                continue;
            }

            $PHPReader = new PHPExcel_Reader_Excel2007();  //建立reader对象
            if(!$PHPReader->canRead($dir . $filePath)){
                $PHPReader = new PHPExcel_Reader_Excel5();
                if(!$PHPReader->canRead($dir . $filePath)){
                    $returnData = array(
                        'code' => 1,
                        'msg' => 'no Excel！',
                    );
                    return $returnData;
                }
            }
            $PHPExcel     = $PHPReader->load($dir . $filePath);       //**建立excel对象
            $currentSheet = $PHPExcel->getSheet(1);       //**读取excel文件中的指定工作表*/
            $rows=$currentSheet->getHighestRow();
            //检测表头是否匹配
            $tableHead = array(
                'A' => 'Node ID',
                'B'=>'Node Path',
                'C'=>'Query',
            );
            foreach($tableHead as $k=>$vvv){
                $kkk=$k.'1';
                $tableHead= trim($currentSheet->getCell($kkk)->getValue());
                if($tableHead!=$vvv){
                    $returnData = array(
                        'code' => 1,
                        'msg' => 'Excel读取失败！检查上传的Excel文件表头是否匹配！建议重新下载模版！',
                    );
                    return $returnData;
                }
            }

            $c=2;
            $db = D('sys_amazon_us_recommended_browse_node_list');
            $category = '';
            $pid = 0;
            $saveData = array();
            while($c<=$rows) {
                $aa = 'A' . $c;
                $bb = 'B' . $c;
                $cc = 'C' . $c;
                $c++;

                $includes = trim($currentSheet->getCell($bb)->getValue());

                if (empty($includes)) {
                    continue;
                }
                $item_type_keyword = trim($currentSheet->getCell($cc)->getValue());
                if (empty($item_type_keyword)) {
                    continue;
                }
                $item_type_keyword = str_replace('item_type_keyword:', '', $item_type_keyword);
                $node_path = trim($currentSheet->getCell($bb)->getValue());
                $saveData[] = [
                    'node_path' => $node_path,
                    'item_type_keyword' => $item_type_keyword,
                ];
            }
            if ($db->addAll($saveData)) {
                echo $filePath . '添加成功。<br/>';
            } else {
                echo $filePath . '添加失败。<br/>';
                die;
            }
        }
        closedir($handler);

    }

}