<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();
if(!CModule::IncludeModule("webservice"))
	return;
if(!CModule::IncludeModule("blog"))
	return;
if(!CModule::IncludeModule("socialnetwork"))
	return;

class CGalleryNewsWS extends IWebService
{
	function GetNews($nTopCount)
	{
		$arResult = array();

		$parser = new blogTextParser();
		$arParserParams = Array(
			"imageWidth" => $arParams["IMAGE_MAX_WIDTH"],
			"imageHeight" => $arParams["IMAGE_MAX_HEIGHT"],
		);

		$SORT = Array("DATE_PUBLISH" => "DESC", "NAME" => "ASC");
		$arFilter = Array(
			//	"CATEGORY_ID_F" => Array(10, 11),
			"SOCNET_GROUP_ID" => 106,
			"PUBLISH_STATUS" => BLOG_PUBLISH_STATUS_PUBLISH
			);	
		$dbPosts = CBlogPost::GetList(
				$SORT,
				$arFilter,
				false,
				Array("nTopCount" => $nTopCount),
				Array("ID", "TITLE", "BLOG_ID", "AUTHOR_ID", "PREVIEW_TEXT", "PREVIEW_TEXT_TYPE", "DETAIL_TEXT", "DETAIL_TEXT_TYPE", "DATE_PUBLISH", "KEYWORDS", "PUBLISH_STATUS", "CATEGORY_ID", "AUTHOR_NAME", "AUTHOR_LAST_NAME")
			);

		$i = 0;
		while ($arPost = $dbPosts->Fetch())
		{

			$arPost["TITLE"] = str_replace(
                            array("&", "<", ">", "\""),
                            array("&amp;", "&lt;", "&gt;", "&quot;"),
                            $arPost["TITLE"]);

			$arImages = Array();
            $res = CBlogImage::GetList(array("ID"=>"ASC"),array("POST_ID"=>$arPost['ID'], "BLOG_ID"=>$arPost['BLOG_ID'], "IS_COMMENT" => "N"));
			$j = 0;
			$arPost["IMAGES"] = array();
            while ($arImage = $res->Fetch()) 
			{
				$j++;
				$arPost["IMAGES"][$j.':IMAGE'] = $arImage;
            	$arImages[$arImage['ID']] = $arImage['FILE_ID'];
			}

			$arPost["DETAIL_TEXT"] = "<![CDATA[".$arPost["DETAIL_TEXT"]."]]>";

		   if($arPost["DETAIL_TEXT_TYPE"] == "html")
				$arPost["DETAIL_TEXT_HTML"] = "<![CDATA[".$parser->convert_to_rss($arPost["DETAIL_TEXT"], $arImages, array("HTML" => "Y", "ANCHOR" => "Y", "IMG" => "Y", "SMILES" => "Y", "NL2BR" => "N", "QUOTE" => "Y", "CODE" => "Y"), true, $arParserParams)."]]>";
			else
				$arPost["DETAIL_TEXT_HTML"] = "<![CDATA[".$parser->convert_to_rss($arPost["DETAIL_TEXT"], $arImages, false, !$bSoNet, $arParserParams)."]]>";

			$arPost["CATEGORY"] = "";
			$dbCategory = CBlogCategory::GetList(array(), array("BLOG_ID"=>$arPost['BLOG_ID'], "ID"=>explode(",", $arPost["CATEGORY_ID"])), false, false, array("NAME"));
			while ($arCategory = $dbCategory->Fetch())
			{
				$arPost["CATEGORY"] .= $arCategory["NAME"]."|";
			}
			$arPost["CATEGORY"] = trim($arPost["CATEGORY"], '|');

			$perm = CBlogPost::getSocnetPermsName($arPost['ID']);
			$j = 0;
			$arPost["DESTINATIONS"] = array();
			foreach ($perm as $type => $v) {
            	foreach ($v as $vv) {
					$j++;
					if ($type == "SG") {
						$socNetGroup = CSocNetGroup::getByID($vv["ENTITY_ID"]);
						$arPost["DESTINATIONS"][$j.':DESTINATION'] = array("ID" => $type."-".$vv["ENTITY_ID"], "NAME" => $socNetGroup["~NAME"]);
					} elseif ($type == "U") {
						$arPost["DESTINATIONS"][$j.':DESTINATION'] = array("ID" => $type."-".$vv["ENTITY_ID"], "NAME" => $vv["~U_NAME"]." ".$vv["~U_LAST_NAME"]);
					} elseif ($type == "DR") {
                    	$arPost["DESTINATIONS"][$j.':DESTINATION'] = array("ID" => $type."-".$vv["ENTITY_ID"], "NAME" => $vv["EL_NAME"]);
                	}
				}
			}

			$i++;
			$arResult[$i.':POST'] = $arPost;
		}

		return $arResult;
	}


	function GetWebServiceDesc()
	{
		$wsdesc = new CWebServiceDesc();
		$wsdesc->wsname = "gallery.webservice.news";
		$wsdesc->wsclassname = "CGalleryNewsWS";
		$wsdesc->wsdlauto = true;
		$wsdesc->wsendpoint = CWebService::GetDefaultEndpoint();
		$wsdesc->wstargetns = CWebService::GetDefaultTargetNS();

		$wsdesc->classTypes = array();

		$wsdesc->structTypes["Post"] =
			array(
				"ID" => array("varType" => "integer"),
				"TITLE" => array("varType" => "string"),
				"BLOG_ID" => array("varType" => "integer"),
				"AUTHOR_ID" => array("varType" => "integer"),
				"PREVIEW_TEXT_TYPE" => array("varType" => "string"),
				"PREVIEW_TEXT" => array("varType" => "string"),
				"DETAIL_TEXT_TYPE" => array("varType" => "string"),
				"DETAIL_TEXT" => array("varType" => "string"),
				"DETAIL_TEXT_HTML" => array("varType" => "string"),
				"DATE_PUBLISH" => array("varType" => "string"),
				"KEYWORDS" => array("varType" => "string"),
				"PUBLISH_STATUS" => array("varType" => "string"),
				"CATEGORY_ID" => array("varType" => "string"),
				"CATEGORY" => array("varType" => "string"),
				"AUTHOR_NAME" => array("varType" => "string"),
				"AUTHOR_LAST_NAME" => array("varType" => "string"),
				"AUTHOR_SECOND_NAME" => array("varType" => "string"),
				"IMAGES" => array("varType" => "ArrayOfImage", "arrType"=>"Image"),
				"DESTINATIONS" => array("varType" => "ArrayOfDestination", "arrType"=>"Destination"),
			);

		$wsdesc->structTypes["Image"] = Array(
			"ID"  => array("varType" => "string"),
			"FILE_ID"  => array("varType" => "string")
			);

		$wsdesc->structTypes["Destination"] = Array(
			"ID"  => array("varType" => "string"),
			"NAME"  => array("varType" => "string")
			);

		$wsdesc->classes = array(
			"CGalleryNewsWS" => array(
				"GetNews" => array(
					"type"		=> "public",
					"name"		=> "GetNews",
					"input"		=> array(
						"nTopCount" => array("varType" => "integer", "strict" => "no")
					),
					"output"	=> array(
						"posts" => array("varType" => "ArrayOfPost", "arrType"=>"Post", "strict" => "no")
					),
				),
			)
		);
		return $wsdesc;
	}
}

$arParams["WEBSERVICE_NAME"] = "gallery.webservice.news";
$arParams["WEBSERVICE_CLASS"] = "CGalleryNewsWS";
$arParams["WEBSERVICE_MODULE"] = "";

$APPLICATION->IncludeComponent(
	"bitrix:webservice.server",
	"",
	$arParams
);

die();
?>
