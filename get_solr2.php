<?php

require "vendor/autoload.php";

function get_conf() {

   return array(
        'endpoint' => array(
            'localhost' => array(
                'host' => 'repositorio.colmex.mx',
                'port' => 8983,
                'path' => '/',
                'core' => 'hydra-indexer',
                // For Solr Cloud you need to provide a collection instead of core:
                // 'collection' => 'techproducts',
            )
        )
    );
}

function get_fields(){
    return array('id',
                'title_tesim',
                'date_created_tesim',
                'creator_conacyt_tesim',
                'contributor_conacyt_tesim',
                'subject_conacyt_tesim',
                'pub_conacyt_tesim',
                'type_conacyt_tesim',
                'subject_tesim',
                'subject_person_tesim',
                'subject_family_tesim',
                'subject_work_tesim',
                'subject_corporate_tesim',
                'license_tesim',
                "human_readable_type_tesim",
                'language_tesim',
                'visibility_ssi',
                "file_set_ids_ssim"
                
            );
}

function extract_attribs($name_conacyt) {
    $segment = explode("\n", $name_conacyt);
    $attr = "";
    $dict = [];

    $dict["nombres"] = "";
    $dict["primerApellido"] = "";
    $dict["segundoApellido"] = "";
    foreach($segment as $s)
    {
        $b = explode(": ",$s);
        
        $dict[$b[0]] = $b[1];
        

    }

    if(array_key_exists("idOrcid", $dict)){
        $attr = "info:eu-repo/dai/mx/orcid/".$dict["idOrcid"];
    }elseif(array_key_exists("idCvuConacyt", $dict)){
        $attr = "info:eu-repo/dai/mx/cvu/".$dict["idCvuConacyt"];
    }elseif(array_key_exists("curp", $dict)){
        $attr = "info:eu-repo/dai/mx/curp/".$dict["curp"];
    }elseif(array_key_exists("idIdentificadorCa", $dict)){
        #$attr = "info:eu-repo/dai/mx/ca/".str_replace("CA","",$dict["idIdentificadorCa"]);
        return false;
    }elseif(array_key_exists("rn", $dict)){
        #$attr = "info:eu-repo/dai/mx/rn/".$dict["rn"];
        return false;
    }
        

    $name_a = array($dict["nombres"]);
    
    array_push($name_a, $dict["primerApellido"]);
    
    if($dict["segundoApellido"] != ""){
    	array_push($name_a, $dict["segundoApellido"]);
    }

    return array(implode(" ",$name_a) => $attr);
}

function add_access_rights($right){
   
    if ($right == "restricted") {
      $value = "restrictedAccess";
    } elseif ( $right == "open" ) {
      $value = "openAccess";
    } elseif ( $right == "embargoed" ){
      $value = "embargoedAccess";
    } else {
      $value = "closedAccess";
    }

    return $value;
}
    

function remap($reg, $iso) {
    $id = "";
    $r = [];
    $attr = [];
    foreach ($reg as $key => $value) {
        $key = str_replace("_tesim", "", $key);
        $key = str_replace("_ssi", "", $key);
        $value = str_replace("&", "&amp;",$value);

        if ($key=="id"){
            $id = $value;
            continue;
        } elseif ($key == "creator_conacyt" || $key == "contributor_conacyt") {
            $key = str_replace("_conacyt", "", $key);
            $i = 0;
            foreach($value as $v)
            {
                $parsed_data = extract_attribs($v);
                
                if($parsed_data == false){
                	return false;
                }
                // krumo($parsed_data);
                $value[$i] = key($parsed_data);
                // krumo($value);
                $attr = array_merge($attr,$parsed_data);
                $i++;
            }
            


        } elseif ($key == "date_created") {
            $key = str_replace("_created", "", $key);
        } elseif ($key == "language") {
            
            $i=0;
            foreach($value as $v) {   
                $value[$i] = $iso->code3ByLanguageEs($v);
                $i++;
            } 
 
        } elseif ($key == "subject_conacyt") {
            $key = "subject";
            $i = 0;
            foreach ($value as $v){
                $value[$i] = "info:eu-repo/classification/cti/".$v; 
                $i++;
            }
           
        } elseif ($key == "subject" || $key == "subject_work" || $key == "subject_person" || $key == "subject_family" || $key == "subject_corporate") {
            $key = "description";
        } elseif ($key == "type_conacyt") {
            $key = "type";
            
            $i = 0;
            foreach($value as $v)
            {
                if($v == "info:eu-repo/semantics/masterDegreeWork") {
                
                    $value[$i] = "info:eu-repo/semantics/masterThesis";
                }
                $i++;
            }

            
        } elseif ($key == "pub_conacyt") {
            $key = "audience";
            $i = 0;
            foreach ($value as $v){
                $value[$i] = strtolower($v);
                $i++;
            }
        } elseif ($key == "license" || $key == "visibility") {
                       
            if ($key == "visibility") {
                $value = array("info:eu-repo/semantics/".add_access_rights($value));
                if (array_key_exists("dc:rights",$r)){
                    $value = array_merge($r["dc:rights"], $value);
                   
                }
            }
            $key = "rights";
        } elseif ($key == "human_readable_type") {
            $key = "identifier";
            $i = 0;
            
            foreach ($value as $v){
                $value[$i] = "http://repositorio.colmex.mx/concern/".pluralize(strtolower($v))."/".$id;
                $i++;
            }
        }
               
        if ($key == "description" && array_key_exists("dc:description", $r)) {
            $value = array_merge($r["dc:".$key], $value);
        }

        $r["dc:".$key] = $value;
    }
    // krumo(array("fields" => $r, "identifier" => $id, 'attributes' => $attr));
    return array("fields" => $r, "identifier" => $id, 'attributes' => $attr);
}


function get_records($start = 0, $rows=20)
{

    $config = get_conf();

    $select = array(
        'query'         => "creator_conacyt_tesim:[* TO *]-creator_conacyt_tesim:'' AND file_set_ids_ssim:[* TO *]-file_set_ids_ssim:'' AND subject_conacyt_tesim:[* TO *]-subject_conacyt_tesim:'' AND type_conacyt_tesim:[* TO *]-type_conacyt_tesim:''" ,
        //'query'         => "creator_conacyt_tesim:[* TO *]-creator_conacyt_tesim:''",

        'start'         => $start,
        'rows'          => $rows,
        'fields'        => get_fields(),
       'filterquery' => array(
           "work_type" => array(
                "query" => 'resource_type_tesim: ("Capítulo de libro")'
                //"query" => 'type_conacyt_tesim: ("info:eu-repo/semantics/masterDegreeWork")'

           ),
            // "lang_type" => array(
            //  "query" => 'language_tesim: ("otomí" OR "sánscrito" OR "chino")'
            // ),
        ),
    );


    $client = $client = new Solarium\Client($config);

    // get a select query instance based on the config
    $query = $client->createSelect($select);

    // this executes the query and returns the result
    $resultset = $client->select($query);

    $data = array($resultset->getDocuments(), $resultset->getNumFound());

    $iso = new Matriphe\ISO639\ISO639;

    $datos_oai = [];
    foreach($data[0] as $d) {
        $reg = remap($d, $iso);
        if($reg != false)
        {
        	array_push($datos_oai,$reg);
        }
    }
    
    return array($datos_oai,$data[1]);
   
}

function get_record($id){

    $config = get_conf();

    $iso = new Matriphe\ISO639\ISO639;

    $select = array(
        'query'         => "id:".$id,        
        'fields'        => get_fields()
    );

    $client = $client = new Solarium\Client($config);

    // get a select query instance based on the config
    $query = $client->createSelect($select);

    // this executes the query and returns the result
    $resultset = $client->select($query);

    return remap($resultset->getDocuments()[0], $iso);
     
}

function pluralize($term){
    switch ($term) {
        case "thesis":
            $x = "theses";
            break;
        case "article":
            $x = "articles";
            break;
        case "book":
            $x = "books";
            break;
        case "chapter":
            $x = "chapters";
            break;
        default:
            $x = $term;

        
        }
    
    return $x;
}

var_dump(get_records(0, 10000));

// echo get_record('xw42n8035')['atributes'][0]; GGB080116EZ0

?>
