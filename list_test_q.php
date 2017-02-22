<?php
$location = "�ű����";
include "../_header.php";
@include "../../conf/config.pay.php";
include "../../lib/page.class.php";
@include "../../conf/phone.php";


if (get_magic_quotes_gpc()) {
	stripslashes_all($_POST);
	stripslashes_all($_GET);
}

// $_GET���� �޴� ��� �� ����
$search=array(
	'first'=>(string)$_GET['first'], // ó�� ���ȭ���� ���������� ���� ��
	'regdt_start'=>(string)$_GET['regdt'][0], // ó������ ����
	'regdt_end'=>(string)$_GET['regdt'][1], // ó������ ��
	'dtkind'=>(string)($_GET['dtkind'] ? $_GET['dtkind'] : 'orddt'), // ó������ ����
	'mode'=>(string)$_GET['mode'], // ��� ���� ( �ֹ��Ϸ� ���� , �ֹ�ó���帧���� ���� )
	'sword'=>trim((string)$_GET['sword']), // �˻���
	'skey'=>($_GET['skey'] ? (string)$_GET['skey'] : 'all'), // �˻��ʵ�
	'sgword'=>trim((string)$_GET['sgword']), // ��ǰ �˻���
	'sgkey'=>(string)$_GET['sgkey'], // ��ǰ �˻��ʵ�
	'step'=>(array)$_GET['step'], // �ֹ�����
	'step2'=>(array)$_GET['step2'], // �ֹ�����
	'settlekind'=>(string)$_GET['settlekind'], // �������
	'escrowyn'=>(string)$_GET['escrowyn'], // ����ũ��
	'eggyn'=>(string)$_GET['eggyn'], // ���ں�������
	'chk_inflow'=>(array)$_GET['chk_inflow'], // ����ó�ֹ�
	'couponyn'=>(string)$_GET['couponyn'], // �������
	'cashreceipt'=>(string)$_GET['cashreceipt'], // ���ݿ�����
	'cbyn'=>(string)$_GET['cbyn'], // OKCashbag����
	'aboutcoupon'=>(string)$_GET['aboutcoupon'], // ��ٿ�����
	'mobilepay'=>(string)$_GET['mobilepay'], // ����ϼ�
	'todaygoods'=>(string)$_GET['todaygoods'], // �����̼�
	'regdt_time_start'=>$_GET['regdt_time'] ? (int)$_GET['regdt_time'][0] : -1, // ó������ �ð���
	'regdt_time_end'=>$_GET['regdt_time'] ? (int)$_GET['regdt_time'][1] : -1, // ó������ �ð���
	'sugi'=>(string)$_GET['sugi'],
	'itemcondition'=>$_GET['itemcondition'],
	's_prn_settleprice'=>(string)$_GET['prn_settleprice'][0], // �����ݾ� ����
	'e_prn_settleprice'=>(string)$_GET['prn_settleprice'][1], // �����ݾ� ��
	'payco'=>(string)$_GET['payco'], // ������
);
$page = (int)$_GET['page'] ? (int)$_GET['page'] : 1;

// first ���ڰ��� ���� ó������ �⺻�� ����
if($search['first']) {
	if(!$cfg['orderPeriod']) $cfg['orderPeriod']=0;
	$search['regdt_start'] = date('Ymd',strtotime('-'.$cfg['orderPeriod'].' day'));
	$search['regdt_end'] = date('Ymd');
}
$search['first']=0;

// ��������
if(!in_array($search['dtkind'],array('orddt','cdt','ddt','confirmdt'))) { exit; }
if(!in_array($search['skey'],array('all','ordno','nameOrder','nameReceiver','bankSender','m_id','mobileOrder'))) { exit; }
if(!in_array($search['sgkey'],array('','goodsnm','brandnm','maker'))) { exit; }
foreach($search['step'] as $k=>$v) { $search['step'][$k]=(int)$v; }
foreach($search['step2'] as $k=>$v) { $search['step2'][$k]=(int)$v; }

// �������� ���� �˻����� �����
$isOrderItemSearch=false;
$arWhere = array();
// ��������
if($search['sugi']) {
	if($search['sugi'] == "Y") $arWhere[] = "o.inflow = 'sugi'";
	elseif($search['sugi'] == "N") $arWhere[] = "o.inflow != 'sugi'";
}
if($search['regdt_start']) {
	if(!$search['regdt_end']) $search['regdt_end'] = date('Ymd');

	$tmp_start = substr($search['regdt_start'],0,4).'-'.substr($search['regdt_start'],4,2).'-'.substr($search['regdt_start'],6,2);
	$tmp_end = substr($search['regdt_end'],0,4).'-'.substr($search['regdt_end'],4,2).'-'.substr($search['regdt_end'],6,2);

	if ($search['regdt_time_start'] !== -1 && $search['regdt_time_end'] !== -1) {
		$tmp_start .= ' '.sprintf('%02d',$search['regdt_time_start']).':00:00';
		$tmp_end .= ' '.sprintf('%02d',$search['regdt_time_end']).':59:59';
	}
	else {
		$tmp_start .= ' 00:00:00';
		$tmp_end .= ' 23:59:59';
	}
	switch($search['dtkind']) {
		case 'orddt': $arWhere[] = $db->_query_print('o.orddt between [s] and [s]',$tmp_start,$tmp_end); break;
		case 'cdt': $arWhere[] = $db->_query_print('o.cdt between [s] and [s]',$tmp_start,$tmp_end); break;
		case 'ddt': $arWhere[] = $db->_query_print('o.ddt between [s] and [s]',$tmp_start,$tmp_end); break;
		case 'confirmdt': $arWhere[] = $db->_query_print('o.confirmdt between [s] and [s]',$tmp_start,$tmp_end); break;
	}
}
if($search['settlekind']) {
	$arWhere[] = $db->_query_print('o.settlekind = [s]',$search['settlekind']);
}
if(count($search['step']) || count($search['step2'])) {
	$subWhere = array();
	if(count($search['step'])) {
		$subWhere[] = '(o.step in ("'.implode('","', $search['step']).'") and o.step2="0")';
	}
	if(count($search['step2'])) {
		foreach($search['step2'] as $k=>$v) {
			switch($v) {
				case 1: $subWhere[] = '(o.step=0 and o.step2 between 1 and 49)'; break;
				case 2: $subWhere[] = '(o.step in (1,2) and o.step2!=0) OR (o.cyn="r" and o.step2="44" and o.dyn!="e")'; break;
				case 3: $subWhere[] = '(o.step in (3,4) and o.step2!=0)'; break;
				case 60 : $subWhere[] = "(oi.dyn='e' and oi.cyn='e')"; $isOrderItemSearch=true; break; //��ȯ�Ϸ�
				case 61 : $subWhere[] = "o.oldordno != ''";break; //���ֹ�
				default : $subWhere[] = "o.step2 = '$v'";
			}
		}
	}
	if(count($subWhere)) {
		$arWhere[] = '('.implode(' or ',$subWhere).')';
	}
}
if($search['sword'] && $search['skey']) {
	$es_sword = $db->_escape($search['sword']);
	switch($search['skey']) {
		case 'all':
			$arWhere[] = "(
				o.ordno = '{$es_sword}' or
				o.nameOrder like '%{$es_sword}%' or
				o.nameReceiver like '%{$es_sword}%' or
				o.bankSender like '%{$es_sword}%' or
				m.m_id = '{$es_sword}' or
				o.mobileOrder like '%{$es_sword}%'
			)"; break;
		case 'ordno': $arWhere[] = "o.ordno = '{$es_sword}'"; break;
		case 'nameOrder': $arWhere[] = "o.nameOrder like '%{$es_sword}%'"; break;
		case 'nameReceiver': $arWhere[] = "o.nameReceiver like '%{$es_sword}%'"; break;
		case 'bankSender': $arWhere[] = "o.bankSender like '%{$es_sword}%'"; break;
		case 'm_id': $arWhere[] = "m.m_id = '{$es_sword}'"; break;
		case 'mobileOrder': $arWhere[] = "o.mobileOrder like '%{$es_sword}%'"; break;
	}
}
if($search['sgword'] && $search['sgkey']) {
	$es_sgword = $db->_escape($search['sgword']);
	switch($search['sgkey']) {
		case 'goodsnm': $arWhere[] = "oi.goodsnm like '%{$es_sgword}%'"; break;
		case 'brandnm': $arWhere[] = "oi.brandnm like '%{$es_sgword}%'"; break;
		case 'maker': $arWhere[] = "oi.maker like '%{$es_sgword}%'"; break;
	}
	$isOrderItemSearch=true;
}

//�ֹ��ݾ� �˻�
if ($search['s_prn_settleprice'] != '' && $search['e_prn_settleprice'] != '')			$arWhere[] = "o.prn_settleprice between ".$search['s_prn_settleprice']." and ".$search['e_prn_settleprice'];
else if ($search['s_prn_settleprice'] != '' &&  $search['e_prn_settleprice'] == '')		$arWhere[] = "o.prn_settleprice >= ".$search['s_prn_settleprice'];
else if ($search['s_prn_settleprice'] == '' && $search['e_prn_settleprice'] != '')		$arWhere[] = "o.prn_settleprice <= ".$search['e_prn_settleprice'];

if(count($search['chk_inflow'])) {
	$es_inflow = array();
	foreach($search['chk_inflow'] as $v) {
		if($v == 'naver_price') {
			$es_inflow[] = '"naver_elec"';
			$es_inflow[] = '"naver_bea"';
			$es_inflow[] = '"naver_milk"';
		}
		else if($v == 'plus_cheese'){
			$arWhere[] = 'o.pCheeseOrdNo<>\'\'';
		}
		else {
			$es_inflow[] = '"'.$db->_escape($v).'"';
		}
	}
	if(!empty($es_inflow)){
		$arWhere[] = 'o.inflow in ('.implode(',',$es_inflow).')';
	}
}
if($search['cbyn']=='Y') {
	$arWhere[] = 'o.cbyn = "Y"';
}
if($search['aboutcoupon']=='1') {
	$arWhere[] = 'o.about_coupon_flag = "Y"';
}
if($search['escrowyn']) {
	$arWhere[] = $db->_query_print('o.escrowyn = [s]',$search['escrowyn']);
}
if($search['eggyn']) {
	$arWhere[] = $db->_query_print('o.eggyn = [s]',$search['eggyn']);
}
if($search['mobilepay']) {
	$arWhere[] = $db->_query_print('o.mobilepay = [s]',$search['mobilepay']);
}
if ($search['todaygoods']) {
	$arWhere[] = $db->_query_print('exists(SELECT * FROM '.GD_ORDER_ITEM.' AS oi JOIN '.GD_GOODS.' AS g ON oi.goodsno=g.goodsno WHERE oi.ordno=o.ordno AND g.todaygoods=[s])',$search[todaygoods]);
}
if($search['cashreceipt']) {
	$arWhere[] = 'o.cashreceipt != ""';
}
if($search['payco']) {
	$arWhere[] = 'o.settleInflow = "payco"';
}
if($search['couponyn']) {
	$arWhere[] = 'co.ordno is not null';
	$join_GD_COUPON_ORDER='left join '.GD_COUPON_ORDER.' as co on o.ordno=co.ordno';
}
else {
	$join_GD_COUPON_ORDER='';
}

// gd_order_item ���� �˻������� �߻��ϴ� ��� ��ǰ������ ��ǰ����üũ�� ������ ó��
if($isOrderItemSearch) {
	$select_count_item = '(select count(*) from '.GD_ORDER_ITEM.' as s_oi where s_oi.ordno=o.ordno) as count_item';
	$select_count_dv_item = '(select count(*) from '.GD_ORDER_ITEM.' as s_oi where s_oi.ordno=o.ordno and s_oi.dvcode!="" and s_oi.dvno!="") as count_dv_item';
}
else {
	$select_count_item = 'count(oi.ordno) as count_item';
	$select_count_dv_item = 'sum(oi.dvcode != "" and oi.dvno != "") as count_dv_item';
}

if(count($arWhere)) {
	$strWhere = 'where '.implode(' and ',$arWhere);
}

// ���� ����
/* ++��ǰ��ȣ �� �׷��� �ϱ� ���� array */
$goodsList=array();
/* --��ǰ��ȣ �� �׷��� �ϱ� ���� array */
$orderList=array();
$orderGroupNameMap=array();
$isEnableAdminCheckoutOrder = @include './checkout.inc.integrate.list.php'; // Checkout include
echo "isEnableAdminCheckoutOrder";
echo $isEnableAdminCheckoutOrder;
if($isEnableAdminCheckoutOrder !== true) {

	$query = '
		select
			o.ordno as ordno,
			o.nameOrder as nameOrder,
			o.nameReceiver as nameReceiver,
			o.settlekind as settlekind,
			o.step as step,
			o.step2 as step2,
			o.orddt as orddt,
			o.dyn as dyn,
			o.escrowyn as escrowyn,
			o.eggyn as eggyn,
			o.inflow as inflow,
			o.deliverycode as deliverycode,
			o.cashreceipt as cashreceipt,
			o.cbyn as cbyn,
			o.oldordno as oldordno,
			o.prn_settleprice as prn_settleprice,
			o.pCheeseOrdNo,
			o.inflow as inflow,
			m.m_id as m_id,
			m.m_no as m_no,
			m.level as level,
			o.settleInflow as settleInflow,
			'.$select_count_item.',
			'.$select_count_dv_item.',
			oi.goodsnm as goodsnm,
			oi.goodsno as goodsno
		from
			'.GD_ORDER.' as o
			left join '.GD_ORDER_ITEM.' as oi on o.ordno=oi.ordno
			left join '.GD_MEMBER.' as m on o.m_no = m.m_no
			'.$join_GD_COUPON_ORDER.'
		'.$strWhere.'
		group by o.ordno
	';

	if($search['mode']=='group') {
		echo "<p>------group---------</p>";
		$result = $db->_select($query);

		// �׷캰�� �ֹ��� �Ҵ�
		foreach($result as $v) {
			$orderGroupKey = $v['step2']*10+($v['step'] === '1' || ($v['step'] === '2' && $v['step2'] > 40) ? 1 : $v['step']);
			$orderGroupNameMap[$orderGroupKey] = getStepMsg($v['step'],$v['step2']);
			$orderList[$orderGroupKey][] = $v;
		}
		ksort($orderList);

		// ����
		foreach($orderList as $orderGroupKey=>$eachOrderGroup) {
			$sortAssistDyn=$sortAssistOrdno=array();
			foreach ($eachOrderGroup as $k => $v) {
				$sortAssistDyn[$k]  = $v['dyn'];
				$sortAssistOrdno[$k] = $v['ordno'];
				$orderList[$orderGroupKey][$k]['stepMsg'] = getStepMsg($v['step'],$v['step2'],$v['ordno']);
			}
			array_multisort($sortAssistDyn,SORT_ASC,$sortAssistOrdno,SORT_DESC,$orderList[$orderGroupKey]);

			$i=0;
			foreach ($eachOrderGroup as $k => $v) {
				$orderList[$orderGroupKey][$k]['_rno'] = count($eachOrderGroup)-($i++);
			}
		}
	}
	else if($search['mode']=='name') {
		echo "<p>------name---------</p>";
		$result = $db->_select($query);
		$goodsListIdx = 0;
		$srchRst=0;
		
		// �׷캰�� �ֹ��� �Ҵ�
		foreach($result as $v) {
			$srchRst=array_search($v['goodsno'], $goodsList);
			if (!$srchRst){
				$srchRst=$goodsListIdx;
			    $goodsList[$goodsListIdx] = $v['goodsno'];
			    $goodsListIdx++;
			}

			$orderGroupKey = $srchRst;
			$orderGroupNameMap[$orderGroupKey] = $v['goodsnm'];
			$orderList[$orderGroupKey][] = $v;
		}
		ksort($orderList);
		// ����
		foreach($orderList as $orderGroupKey=>$eachOrderGroup) {
			$sortAssistDyn=$sortAssistOrdno=array();
			foreach ($eachOrderGroup as $k => $v) {
				$sortAssistDyn[$k]  = $v['dyn'];
				$sortAssistOrdno[$k] = $v['ordno'];
				$orderList[$orderGroupKey][$k]['stepMsg'] = getStepMsg($v['step'],$v['step2'],$v['ordno']);
			}
			array_multisort($sortAssistDyn,SORT_ASC,$sortAssistOrdno,SORT_DESC,$orderList[$orderGroupKey]);

			$i=0;
			foreach ($eachOrderGroup as $k => $v) {
				$orderList[$orderGroupKey][$k]['_rno'] = count($eachOrderGroup)-($i++);
			}
		}
	}
	else {
		echo "<p>------else---------</p>";
		if(!$cfg['orderPageNum']) $cfg['orderPageNum'] = 15;

		//$query = $query.' order by o.ordno desc';
		$query = $query.' order by goodsnm desc';
		
		$result = $db->_select_page($cfg['orderPageNum'],$page,$query);

		$orderList[9999]=array();
		foreach($result['record'] as $v) {
			$v['stepMsg']=getStepMsg($v['step'],$v['step2'],$v['ordno']);
			$orderList[9999][] = $v;
		}
		$pageNavi = $result['page'];
	}
}

### �׷�� ��������
$r_grp = array();
$garr = member_grp();
foreach( $garr as $v ) $r_grp[$v['level']] = $v['grpnm'];
?>
