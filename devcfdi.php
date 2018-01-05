<?php
//programa que revisa varios archivos XML (CFDI) y genera lista con datos para revisar contabilidad
$separa = ";";
//encabezado
foreach (glob("./cfdissat/*.xml") as $nombre_fichero)
	{
	$strEntrada = file_get_contents($nombre_fichero);
	//$strSalida = extraeCFDICompleto($strEntrada);
	$strSalida = extraeCFDIconClase($strEntrada);
    //echo str_replace("\n","",$strSalida) . "\r\n";
    echo $strSalida . "\r\n";
    }

function extraeCFDIconClase($cadena){
	$c = new CFDI($cadena);
	$c->verifica();
	return ""
		. $c->getTipoComprobante() . ";"
		. $c->getFechaHora() . ";"
		. $c->getVersion() . ";"
		. $c->getEmisorRFC() .";"
		. $c->getImporte() .";"
		. $c->getIVA() .";"
		. $c->getTotal() . ";" 
		. $c->getPagoForma() . ";" 
		. $c->getPagoMetodo() . ";" 		
		. $c->getPagoCondiciones() . ";" 		
		. $c->getConceptos() . ";" 
		. $c->getComentarios() .";";
}

class CFDI {
	private $xml;
	private $uuid;
	private $version;
	private $fecha;
	private $hora;
	private $serie;
	private $folio;
	private $emisor_rfc;
	private $emisor_nombre;
	private $receptor_rfc;
	private $receptor_nombre;
	private $fechahora;
	private $tipocomprobante;
	private $importe;
	private $iva;
	private $total;
	private $conceptos;
	private $comentarios;
	private $pago_forma;
	private $pago_condiciones;
	private $pago_metodo;
	function __construct($cadena){
		$this->conceptos = "[";
		$this->xml = $cadena;
		$lector = new XMLReader();
		$lector->xml($this->xml);
		while ($lector->read()){
			if($lector->nodeType == XMLReader::ELEMENT){
				switch ($lector->name) {
					case 'cfdi:Comprobante' :
						$comprobante = new SimpleXMLElement($lector->readOuterXml());
						$atributos = $comprobante->attributes();
						$arr_atributos = json_decode(json_encode($atributos), TRUE);
						$arr_atributos = array_change_key_case($arr_atributos['@attributes']);
						$this->version = $arr_atributos['version'];
						switch($this->version){
							case '3.2' :
								$CVE_PAGO_FRM = 'formadepago';
								$CVE_PAGO_MTD = 'metododepago';
								$CVE_PAGO_CON = 'condicionesdepago';
								break;
							case '3.3' :
								$CVE_PAGO_FRM = 'formapago';
								$CVE_PAGO_MTD = 'metodopago';
								$CVE_PAGO_CON = 'condicionespago';
								break;
						}
						$this->serie = array_key_exists('serie',$arr_atributos) ? $arr_atributos['serie'] : "";
						$this->folio = array_key_exists('folio',$arr_atributos) ? $arr_atributos['folio'] : "";
						$fecha = DateTime::createFromFormat("Y-m-d\TH:i:s",$arr_atributos['fecha']);
						$this->fechahora = $fecha;
						$this->tipocomprobante = $arr_atributos['tipodecomprobante'];
						$this->importe = $arr_atributos['subtotal'];
						$this->total = $arr_atributos['total'];
						$this->pago_forma = array_key_exists($CVE_PAGO_FRM, $arr_atributos) ? $arr_atributos[$CVE_PAGO_FRM] : "";
						$this->pago_metodo = array_key_exists($CVE_PAGO_MTD,$arr_atributos) ? $arr_atributos[$CVE_PAGO_MTD] : "";
						$this->pago_condiciones = array_key_exists($CVE_PAGO_CON, $arr_atributos) ? $arr_atributos[$CVE_PAGO_CON] : "";	
						break;
					case 'cfdi:Emisor' :
						$emisor = new SimpleXMLElement($lector->readOuterXml());
						$atributos = $emisor->attributes();
						$arr_atributos = json_decode(json_encode($atributos), TRUE);
						$arr_atributos = array_change_key_case($arr_atributos['@attributes']);
						$this->emisor_nombre = $arr_atributos['nombre'];
						$this->emisor_rfc = $arr_atributos['rfc'];
						break;
					case 'cfdi:Receptor' :
						$receptor = new SimpleXMLElement($lector->readOuterXml());
						$atributos = $receptor->attributes();
						$arr_atributos = json_decode(json_encode($atributos), TRUE);
						$arr_atributos = array_change_key_case($arr_atributos['@attributes']);
						$this->receptor_nombre = $arr_atributos['nombre'];
						$this->receptor_rfc = $arr_atributos['rfc'];
						break;
					case 'cfdi:Impuestos' :
						if($lector->hasAttributes){
							$traslado = new SimpleXMLElement($lector->readOuterXml());
							$atributos = $traslado->attributes();
							$arr_atributos = json_decode(json_encode($atributos), TRUE);
							$arr_atributos = array_change_key_case($arr_atributos['@attributes']);
							$this->iva = array_key_exists('totalimpuestostrasladados',$arr_atributos) ? $arr_atributos['totalimpuestostrasladados'] : "";	
						}
						break;
					case 'cfdi:Concepto' :
						$concepto = new SimpleXMLElement($lector->readOuterXml());
						$atributos = $concepto->attributes();
						$arr_atributos = json_decode(json_encode($atributos), TRUE);
						$arr_atributos = array_change_key_case($arr_atributos['@attributes']);
						$this->conceptos .= "(" . $arr_atributos['descripcion'] . ")";
						break;
					case 'pago10:DoctoRelacionado' :
						$docrel = new SimpleXMLElement($lector->readOuterXml());
						$atributos = $docrel->attributes();
						$arr_atributos = json_decode(json_encode($atributos), TRUE);
						$arr_atributos = array_change_key_case($arr_atributos['@attributes']);
						$this->conceptos .= "(cfdi pagado:".$arr_atributos['iddocumento'] ." por ".$arr_atributos['imppagado'].")";
					case 'tfd:TimbreFiscalDigital' :
						$timbre = new SimpleXMLElement($lector->readOuterXml());
						$atributos = $timbre->attributes();
						$this->uuid = $atributos->UUID;
						break;
					}
			}
		}
	$this->conceptos .= "]";
	}
	function verifica(){
		$v_total = floatval($this->total);
		$v_importe = $v_total / 1.16;
		$v_iva = $v_importe * 0.16;
		$this->comentarios = "[";
		if(abs(floatval($this->importe)-$v_importe)>0.50){
			$this->comentarios .= "(ERR DIFF:Importe>0.50)";
		}
		if(abs(floatval($this->iva)-$v_iva)>0.50){
			$this->comentarios .= "(ERR DIFF:IVA>0.50)";	
		}
		$this->comentarios .= "]";
	}
	function getUUID(){
		return $this->uuid;
	}
	function getVersion(){
		return $this->version;
	}
	function getEmisorRFC(){
		return $this->emisor_rfc;
	}
	function getEmisorNombre(){
		return $this->emisor_nombre;
	}
	function getReceptorRFC(){
		return $this->receptor_rfc;
	}
	function getReceptorNombre(){
		return $this->receptor_nombre;
	}
	function getFolio(){
		return $this->folio;
	}
	function getSerie(){
		return $this->serie;
	}
	function getFechaHora(){
		return $this->fechahora->format("Y-m-d H:i:s");
	}	
	function getTipoComprobante(){
		return $this->tipocomprobante;
	}	
	function getImporte(){
		return $this->importe;
	}	
	function getIVA(){
		return $this->iva;
	}	
	function getTotal(){
		return $this->total;
	}
	function getConceptos(){
		return $this->conceptos;
	}	
	function getComentarios(){
		return $this->comentarios;
	}		
	function getPagoForma(){
		return $this->pago_forma;
	}		
	function getPagoCondiciones(){
		return $this->pago_condiciones;
	}		
	function getPagoMetodo(){
		return $this->pago_metodo;
	}		
}

?>
