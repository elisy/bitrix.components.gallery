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
	function GetFile($fileId)
	{
		//$file = Bitrix\Disk\File::loadById(34588);
		$file = CFile::GetFileArray($fileId);

		$data = file_get_contents($_SERVER["DOCUMENT_ROOT"].$file["SRC"]);

		return array(
			"ID" => $file["ID"],
			"TIMESTAMP_X" => $file["TIMESTAMP_X"],
			"HEIGHT" => $file["HEIGHT"],
			"WIDTH" => $file["WIDTH"],
			"FILE_SIZE" => $file["FILE_SIZE"],
			"CONTENT_TYPE" => $file["CONTENT_TYPE"],
			"ORIGINAL_NAME" => $file["ORIGINAL_NAME"],
			"CONTENT" => base64_encode($data),
			//"CONTENT" => $_SERVER["DOCUMENT_ROOT"].$file["SRC"],
		);
	}

	function GetNews($nTopCount)
	{
		$arResult = array();

		$parser = new blogTextParser();
		$arParserParams = Array(
			"imageWidth" => $arParams["IMAGE_MAX_WIDTH"],
			"imageHeight" => $arParams["IMAGE_MAX_HEIGHT"],
		);


		$userFieldManager = Bitrix\Disk\Driver::getInstance()->getUserFieldManager();
		list($connectorClass, $moduleId) = $userFieldManager->getConnectorDataByEntityType('BLOG_POST');

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

			$attachments = Bitrix\Disk\AttachedObject::getModelList(array("filter" => array('=ENTITY_TYPE' => $connectorClass, 'ENTITY_ID' => array($arPost['ID']), '=MODULE_ID' => $moduleId), 'with' => array('OBJECT')));
			$j = 0;
			$arPost["ATTACHMENTS"] = array();
            foreach ($attachments as $value)
			{
				$j++;
				$arValue = array("OBJECT_ID" => $value->getObjectId(), "ID" => $value->getId(), "FILE_ID" => $value->getFile()->getFileId());
				$arPost["ATTACHMENTS"][$j.':ATTACHMENT'] = $arValue;
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
				"ATTACHMENTS" => array("varType" => "ArrayOfAttachment", "arrType"=>"Attachment"),
				"DESTINATIONS" => array("varType" => "ArrayOfDestination", "arrType"=>"Destination"),
			);

		$wsdesc->structTypes["Attachment"] = Array(
			"OBJECT_ID"  => array("varType" => "string"),
			"ID"  => array("varType" => "string"),
			"FILE_ID"  => array("varType" => "string")
		);

		$wsdesc->structTypes["Destination"] = Array(
			"ID"  => array("varType" => "string"),
			"NAME"  => array("varType" => "string")
		);

		$wsdesc->structTypes["File"] = Array(
			"ID"  => array("varType" => "string"),
			"TIMESTAMP_X"  => array("varType" => "string"),
			"HEIGHT"  => array("varType" => "integer"),
			"WIDTH"  => array("varType" => "integer"),
			"FILE_SIZE"  => array("varType" => "integer"),
			"CONTENT_TYPE"  => array("varType" => "string"),
			"CONTENT"  => array("varType" => "string"),
			"ORIGINAL_NAME"  => array("varType" => "string"),
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
				"GetFile" => array(
					"type"		=> "public",
					"name"		=> "GetFile",
					"input"		=> array(
						"fileId" => array("varType" => "integer", "strict" => "no"),
					),
					"output"	=> array(
						"content" => array("varType" => "File", "strict" => "no")
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