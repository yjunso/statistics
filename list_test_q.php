<?php
$location = "신규통계";
include "../_header.php";
@include "../../conf/config.pay.php";
include "../../lib/page.class.php";
@include "../../conf/phone.php";


if (get_magic_quotes_gpc()) {
	stripslashes_all($_POST);
	stripslashes_all($_GET);
}

// $_GET으로 받는 모든 값 정의
$search=array(
	'first'=>(string)$_GET['first'], // 처음 목록화면을 열었는지에 대한 값
	'regdt_start'=>(string)$_GET['regdt'][0], // 처리일자 시작
	'regdt_end'=>(string)$_GET['regdt'][1], // 처리일자 끝
	'dtkind'=>(string)($_GET['dtkind'] ? $_GET['dtkind'] : 'orddt'), // 처리일자 종류
	'mode'=>(string)$_GET['mode'], // 목록 형식 ( 주문일로 보기 , 주문처리흐름으로 보기 )
	'sword'=>trim((string)$_GET['sword']), // 검색어
	'skey'=>($_GET['skey'] ? (string)$_GET['skey'] : 'all'), // 검색필드
	'sgword'=>trim((string)$_GET['sgword']), // 상품 검색어
	'sgkey'=>(string)$_GET['sgkey'], // 상품 검색필드
	'step'=>(array)$_GET['step'], // 주문상태
	'step2'=>(array)$_GET['step2'], // 주문상태
	'settlekind'=>(string)$_GET['settlekind'], // 결제방법
	'escrowyn'=>(string)$_GET['escrowyn'], // 에스크로
	'eggyn'=>(string)$_GET['eggyn'], // 전자보증보험
	'chk_inflow'=>(array)$_GET['chk_inflow'], // 제휴처주문
	'couponyn'=>(string)$_GET['couponyn'], // 쿠폰사용
	'cashreceipt'=>(string)$_GET['cashreceipt'], // 현금영수증
	'cbyn'=>(string)$_GET['cbyn'], // OKCashbag적립
	'aboutcoupon'=>(string)$_GET['aboutcoupon'], // 어바웃쿠폰
	'mobilepay'=>(string)$_GET['mobilepay'], // 모바일샵
	'todaygoods'=>(string)$_GET['todaygoods'], // 투데이샵
	'regdt_time_start'=>$_GET['regdt_time'] ? (int)$_GET['regdt_time'][0] : -1, // 처리일자 시간대
	'regdt_time_end'=>$_GET['regdt_time'] ? (int)$_GET['regdt_time'][1] : -1, // 처리일자 시간대
	'sugi'=>(string)$_GET['sugi'],
	'itemcondition'=>$_GET['itemcondition'],
	's_prn_settleprice'=>(string)$_GET['prn_settleprice'][0], // 결제금액 시작
	'e_prn_settleprice'=>(string)$_GET['prn_settleprice'][1], // 결제금액 끝
	'payco'=>(string)$_GET['payco'], // 페이코
);
$page = (int)$_GET['page'] ? (int)$_GET['page'] : 1;

// first 인자값에 대한 처리일자 기본값 정의
if($search['first']) {
	if(!$cfg['orderPeriod']) $cfg['orderPeriod']=0;
	$search['regdt_start'] = date('Ymd',strtotime('-'.$cfg['orderPeriod'].' day'));
	$search['regdt_end'] = date('Ymd');
}
$search['first']=0;

// 변수검증
if(!in_array($search['dtkind'],array('orddt','cdt','ddt','confirmdt'))) { exit; }
if(!in_array($search['skey'],array('all','ordno','nameOrder','nameReceiver','bankSender','m_id','mobileOrder'))) { exit; }
if(!in_array($search['sgkey'],array('','goodsnm','brandnm','maker'))) { exit; }
foreach($search['step'] as $k=>$v) { $search['step'][$k]=(int)$v; }
foreach($search['step2'] as $k=>$v) { $search['step2'][$k]=(int)$v; }

// 쿼리문을 위한 검색조건 만들기
$isOrderItemSearch=false;
$arWhere = array();
// 접수유형
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
				case 60 : $subWhere[] = "(oi.dyn='e' and oi.cyn='e')"; $isOrderItemSearch=true; break; //교환완료
				case 61 : $subWhere[] = "o.oldordno != ''";break; //재주문
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

//주문금액 검색
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

// gd_order_item 에서 검색조건이 발생하는 경우 상품갯수와 상품송장체크는 별도로 처리
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

// 쿼리 실행
/* ++상품번호 별 그룹핑 하기 위한 array */
$goodsList=array();
/* --상품번호 별 그룹핑 하기 위한 array */
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

		// 그룹별로 주문서 할당
		foreach($result as $v) {
			$orderGroupKey = $v['step2']*10+($v['step'] === '1' || ($v['step'] === '2' && $v['step2'] > 40) ? 1 : $v['step']);
			$orderGroupNameMap[$orderGroupKey] = getStepMsg($v['step'],$v['step2']);
			$orderList[$orderGroupKey][] = $v;
		}
		ksort($orderList);

		// 정렬
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
		
		// 그룹별로 주문서 할당
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
		// 정렬
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

### 그룹명 가져오기
$r_grp = array();
$garr = member_grp();
foreach( $garr as $v ) $r_grp[$v['level']] = $v['grpnm'];
?>
