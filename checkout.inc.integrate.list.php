<?php
/**
 * 주문리스트에서 네이버체크아웃 주문데이터
 * @author sunny, oneorzero
 */

/*
 * CheckoutAPI 환경변수
*/
$config = Core::loader('config');
$checkoutapi = $config->load('checkoutapi');

/*
 * CheckoutAPI 주문 연동 통합관리를 사용하는 경우
*/
if(!($checkoutapi['cryptkey'] && $checkoutapi['integrateOrder']=='y')) return false;	// include 이므로 return 해도 됨..

// 체크아웃 4.0 설정
$checkout_message_schema = include "./_cfg.checkout.php";

$checkout_arWhere = array();
$checkout_isUnableCondition=false;

if($search['regdt_start']) {
	if(!$search['regdt_end']) $search['regdt_end'] = date('Ymd');
	$tmp_start = substr($search['regdt_start'],0,4).'-'.substr($search['regdt_start'],4,2).'-'.substr($search['regdt_start'],6,2).' 00:00:00';
	$tmp_end = substr($search['regdt_end'],0,4).'-'.substr($search['regdt_end'],4,2).'-'.substr($search['regdt_end'],6,2).' 23:59:59';
	switch($search['dtkind']) {
		case 'orddt': $checkout_arWhere[] = $db->_query_print('O.OrderDate between [s] and [s]',$tmp_start,$tmp_end); break;
		case 'cdt': $checkout_arWhere[] = $db->_query_print('O.PaymentDate between [s] and [s]',$tmp_start,$tmp_end); break;
		case 'ddt': $checkout_arWhere[] = $db->_query_print('D.SendDate between [s] and [s]',$tmp_start,$tmp_end); break;
		case 'confirmdt': $checkout_arWhere[] = $db->_query_print('D.DeliveredDate between [s] and [s]',$tmp_start,$tmp_end); break;
	}
}

if($search['settlekind']) {
	$tmpMap = array('a'=>'무통장입금','c'=>'신용카드','o'=>'계좌이체');
	if(array_key_exists($search['settlekind'],$tmpMap)) {
		$checkout_arWhere[] = $db->_query_print('O.PaymentMeans = [s]',$tmpMap[$search['settlekind']]);
	}
	else {
		$checkout_isUnableCondition=true;
	}
}

// 주문 상태별 검색절
$tmp_checkout_arWhere = array();
if(sizeof($search['step']) > 0) { foreach ($search['step'] as $_step) {
	switch ((int)$_step) {
		case 0:	// 주문접수 (입금대기)
			$tmp_checkout_arWhere[] = "(PO.ProductOrderStatus = 'PAYMENT_WAITING' AND PO.ClaimStatus = '')";
			break;
		case 1:	// 입금확인 (발주확인전)
			$tmp_checkout_arWhere[] = "(PO.ProductOrderStatus = 'PAYED' AND PO.PlaceOrderStatus = 'NOT_YET' AND PO.ClaimStatus = '')";
			break;
		case 2:	// 배송준비중(발주확인)
			$tmp_checkout_arWhere[] = "(PO.ProductOrderStatus = 'PAYED' AND PO.PlaceOrderStatus = 'OK' AND PO.ClaimType = '')";
			break;
		case 3:	// 배송중
			$tmp_checkout_arWhere[] = "(PO.ProductOrderStatus = 'DELIVERING' AND PO.ClaimStatus = '')";
			break;
		case 4:	// 배송완료, 구매확정
			$tmp_checkout_arWhere[] = "(PO.ClaimStatus = '' AND (PO.ProductOrderStatus = 'DELIVERED' OR PO.ProductOrderStatus = 'PURCHASE_DECIDED'))";
			break;
	}
}}

if(sizeof($search['step2']) > 0) { foreach ($search['step2'] as $_step) {
	switch ((int)$_step) {
		case 1:	// 취소 (취소요청, 취소처리중, 취소완료)
			$tmp_checkout_arWhere[] = "PO.ClaimType = 'CANCEL'";
			break;
		case 2:
			// 환불 단계 없음

			break;
		case 3:	// 반품 (반품요청, 반품수거중, 반품수거완료, 반품완료)
			$tmp_checkout_arWhere[] = "PO.ClaimType = 'RETURN'";
			break;
		case 60:// 교환 (교환요청, 교환수거중, 교환수거완료, 교환재배송중, 교환완료)
			$tmp_checkout_arWhere[] = "PO.ClaimType = 'EXCHANGE'";
			break;
	}
}}

if(sizeof($search['step']) > 0 || sizeof($search['step2']) > 0) {
	if(sizeof($tmp_checkout_arWhere) > 0) {
		$checkout_arWhere[] = '('.implode(' OR ',$tmp_checkout_arWhere).')';
	}
	else {
		$checkout_isUnableCondition=true;
	}
}

if($search['sword'] && $search['skey']) {
	$es_sword = $db->_escape($search['sword']);
	switch($search['skey']) {
		case 'all':
			$checkout_arWhere[] = "(
				O.OrderID = '{$es_sword}' or
				O.OrdererName like '%{$es_sword}%' or
				PO.ShippingAddressName like '%{$es_sword}%' or
				O.OrdererID = '{$es_sword}'
			)"; break;
		case 'ordno': $checkout_arWhere[] = "O.OrderID = '{$es_sword}'"; break;
		case 'nameOrder': $checkout_arWhere[] = "O.OrdererName like '%{$es_sword}%'"; break;
		case 'nameReceiver': $checkout_arWhere[] = "PO.ShippingAddressName like '%{$es_sword}%'"; break;
		case 'm_id': $checkout_arWhere[] = "O.OrdererID = '{$es_sword}'"; break;
	}
}
if($search['sgword'] && $search['sgkey']) {
	$es_sgword = $db->_escape($search['sgword']);
	switch($search['sgkey']) {
		case 'goodsnm': $checkout_arWhere[] = "PO.ProductName like '%{$es_sgword}%'"; break;
		default: $checkout_isUnableCondition=true;
	}
}

if($checkout_isUnableCondition) {
	$checkout_arWhere[] = '0';
}

if(count($checkout_arWhere)) {
	$checkout_strWhere = 'where '.implode(' and ',$checkout_arWhere);
}

if($search['mode']=='group') {
	$SQL_CALC_FOUND_ROWS='';
}
else {
	$SQL_CALC_FOUND_ROWS='SQL_CALC_FOUND_ROWS';
}

$query = '
	(
		select '.$SQL_CALC_FOUND_ROWS.'
			"godo" as _order_type,
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
			o.settleInflow as settleInflow,
			m.m_id as m_id,
			m.m_no as m_no,
			m.level as level,
			'.$select_count_item.',
			'.$select_count_dv_item.',
			oi.goodsnm as goodsnm,
			null AS PlaceOrderStatus,
			null AS ProductOrderStatus,
			null AS ClaimType,
			null AS ClaimStatus,
			null AS ProductOrderIDList

		from
			'.GD_ORDER.' as o
			left join '.GD_ORDER_ITEM.' as oi on o.ordno=oi.ordno
			left join '.GD_MEMBER.' as m on o.m_no = m.m_no
			'.$join_GD_COUPON_ORDER.'
		'.$strWhere.'
		group by o.ordno
	)
	union
	(
		select
			"checkout" as _order_type,
			O.OrderID as ordno,
			O.OrdererName as nameOrder,
			PO.ShippingAddressName as nameReceiver,
			O.PaymentMeans as settlekind,
			PO.ProductOrderStatus as step,
			null as step2,
			O.OrderDate as orddt,
			"n" as dyn,
			null as escrowyn,
			null as eggyn,
			null as inflow,
			null as deliverycode,
			null as cashreceipt,
			null as cbyn,
			null as oldordno,
			SUM(PO.TotalPaymentAmount) AS prn_settleprice,
			null as settleInflow,
			MB.m_id as m_id,
			MB.m_no as m_no,
			MB.level as level,
			COUNT(PO.ProductOrderID) AS count_item,
			null as count_dv_item,
			PO.ProductName as goodsnm,

			PO.PlaceOrderStatus,
			PO.ProductOrderStatus,
			PO.ClaimType,
			PO.ClaimStatus,
			GROUP_CONCAT(PO.ProductOrderID SEPARATOR ",") AS ProductOrderIDList

		FROM '.GD_NAVERCHECKOUT_ORDERINFO.' AS O

		INNER JOIN '.GD_NAVERCHECKOUT_PRODUCTORDERINFO.' AS PO
			ON PO.OrderID = O.OrderID

		LEFT JOIN '.GD_MEMBER.' AS MB
			ON PO.MallMemberID=MB.m_id

		LEFT JOIN '.GD_NAVERCHECKOUT_DELIVERYINFO.' AS D
			ON PO.ProductOrderID = D.ProductOrderID

		LEFT JOIN '.GD_NAVERCHECKOUT_CANCELINFO.' AS C
			ON PO.ProductOrderID = C.ProductOrderID

		LEFT JOIN '.GD_NAVERCHECKOUT_RETURNINFO.' AS R
			ON PO.ProductOrderID = R.ProductOrderID

		LEFT JOIN '.GD_NAVERCHECKOUT_EXCHANGEINFO.' AS E
			ON PO.ProductOrderID = E.ProductOrderID

		LEFT JOIN '.GD_NAVERCHECKOUT_DECISIONHOLDBACKINFO.' AS DH
			ON PO.ProductOrderID = DH.ProductOrderID

		'.$checkout_strWhere.'

		GROUP BY PO.OrderID, PO.ProductOrderStatus, PO.ClaimStatus
	)
';

if($search['mode']=='group') {
	$result = $db->_select($query);

	// 그룹별로 주문서 할당
	foreach($result as $v) {
		if($v['_order_type']=='godo') {
			$orderGroupKey = $v['step2']*10+($v['step'] === '1' || ($v['step'] === '2' && $v['step2'] > 40) ? 1 : $v['step']);
			$orderGroupNameMap[$orderGroupKey] = getStepMsg($v['step'],$v['step2']);

			$orderList[$orderGroupKey][] = $v;
		}
		elseif($v['_order_type']=='checkout') {
			$_step_msg = getCheckoutOrderStatus($v);
			$_step_key = array_search($_step_msg, $r_step);

			if ($_step_key === false || $_step_key === null)  {
				$orderGroupKey = $_step_msg;
			}
			else {
				$orderGroupKey = $_step_key;
			}

			$orderGroupNameMap[$orderGroupKey] = $_step_msg;
			$orderList[$orderGroupKey][] = $v;
		}
	}
	ksort($orderList);
	foreach($orderList as $orderGroupKey=>$eachOrderGroup) {
		$sortAssistDyn=$sortAssistOrddt=array();
		foreach ($eachOrderGroup as $k => $v) {
			$sortAssistDyn[$k]  = $v['dyn'];
			$sortAssistOrddt[$k] = $v['orddt'];
			if($v['_order_type']=='godo') {
				$orderList[$orderGroupKey][$k]['stepMsg'] = getStepMsg($v['step'],$v['step2'],$v['ordno']);
			}
		}
		array_multisort($sortAssistDyn,SORT_ASC,$sortAssistOrddt,SORT_DESC,$orderList[$orderGroupKey]);

		$i=0;
		foreach ($eachOrderGroup as $k => $v) {
			$orderList[$orderGroupKey][$k]['_rno'] = count($eachOrderGroup)-($i++);
		}
	}
}
else {
	if(!$cfg['orderPageNum']) $cfg['orderPageNum'] = 15;

	$query = $query.' order by orddt desc';
	$result = $db->_select_page($cfg['orderPageNum'],$page,$query);

	$orderList[9999]=array();
	foreach($result['record'] as $v) {
		if($v['_order_type']=='godo') {
			$v['stepMsg']=getStepMsg($v['step'],$v['step2'],$v['ordno']);
		}
		$orderList[9999][] = $v;
	}
	$pageNavi = $result['page'];
}
?>

<script type="text/javascript">
// 배송방법, 택배사 change 이벤트 bind
document.observe("dom:loaded", function() {
	$$("select.el-DeliveryMethodCode").each(function(el){
		Event.observe(el, 'change', function(e) {
			var _el = event.srcElement;

			if (_el.value == 'DELIVERY') {
				_el.next('select',0).writeAttribute('disabled',false);	_el.next('input',0).writeAttribute('disabled',false);
			}
			else {
				_el.next('select',0).writeAttribute('disabled',true);	_el.next('input',0).writeAttribute('disabled',true);
			}
		});
	});
});

/**
* 체크아웃의 주문상태변경수정
*/
function processCheckoutOrder(f, selCase, isGodoChk) {

	// 처리할 체크아웃 주문건이 있나용?
	if ($$('input[name^="PlaceProductOrder_OrderID"]:checked').size() > 0 || $$('input[name^="ShipProductOrder_OrderID"]:checked').size() > 0) {

		//
		var _sel = selCase.value + '';

		if (_sel == '2') {	// 배송준비중 (발주확인)

			// 다른게 섞여 있나용?
			if ($$('input[name^="ShipProductOrder_OrderID"]:checked').size() > 0) {
				alert('배송준비중(발주) 처리 할 수 없는 체크아웃 주문건이 포함되어 있습니다.');
				return false;
			}

		}
		else if (_sel == '3') {	// 배송중(발송)

			// 다른게 섞여 있나용?
			if ($$('input[name^="PlaceProductOrder_OrderID"]:checked').size() > 0) {
				alert('배송중(발송) 처리 할 수 없는 체크아웃 주문건이 포함되어 있습니다.');
				return false;
			}

			// 배송일, 배송방법, 택배사, 송장번호 체크.
			try
			{
				var idx = 0;
				$$('input.chk_ordno').each(function(el) {
					idx++;
					if (el.checked) {
						if ($$('input[name="DispatchDate['+idx+']"]').pop().value == '') throw '배송일을 입력해 주세요.';
						if ($$('select[name="DeliveryMethodCode['+idx+']"]').pop().value == '') throw '배송방법을 선택해 주세요.';

						if ($$('select[name="DeliveryMethodCode['+idx+']"]').pop().value == 'DELIVERY') {
							if ($$('select[name="DeliveryCompanyCode['+idx+']"]').pop().value == '') throw '택배사를 선택해 주세요.';
							if ($$('input[name="TrackingNumber['+idx+']"]').pop().value == '') throw '송장번호를 입력해 주세요.';
						}
					}
				});
			}
			catch (e) {
				if (typeof e == 'string') alert(e);
				return false;
			}
		}
		else {

			var _operation = '';

			switch (_sel) {
				case '0':
					_operation = '주문접수';
					break;
				case '1':
					_operation = '입금확인';
					break;
				case '4':
					_operation = '배송완료';
					break;
			}

			alert('체크아웃 주문은 ' + _operation + '처리 할 수 없습니다.');
			return false;

		}

		var myAjax = new Ajax.Request("./ax.checkout.api.process.php",{
			"method":"post",
			"parameters":f.serialize(true),
			"onComplete":function(transport){

				if (transport.responseText != 'OPERATION_IS_NOT_FOUND') {
					alert(transport.responseText);
				}

				if(isGodoChk) {
					f.submit();
				}
				else {
					self.location.href=self.location.href;
				}
			}
		});

		return false;

		//
	}

	return true;

}
</script>

<?
return true;
?>
