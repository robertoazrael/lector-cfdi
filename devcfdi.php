<?php
//Programa realizado por Roberto Medina (roberto@abcweb.mx), CDMX2018
//
//programa que revisa un directorio buscando los archivos XML (CFDI) que existan ahí
//y genera lista de CFDIS, (archivo.csv) pero sólo con algunos datos (en el orden deseado) del CFDI
// (un formato deseado, pero podría cambiarse a F2, F3, etc.)
//para poder revisar los CFDI y utilizar para contabilidad, para enviar al contador en un Excel

//Se instaancia la clase, podría recibir una cadena conteniendo todo el XML, pero aquí
//se instancia sólo para generar una cadena de encabezados de los datos a obtener
$c = new CFDI;

//se obtiene el encabezado de un formato predefinido, se nombró F1,
//porían generase las cambinaciones deseadas (quizá F2, F3, etc.)
//con el orden deseado y omitiendo o incluyendo los campos deseados, depende de objetivo final
echo $c->getResumenF1Encabezado() . "\r\n";

//se recorre cada archivo xml del directorio SAT
foreach (glob("./SAT/*.xml") as $nombre_fichero)
	{
	$strEntrada = file_get_contents($nombre_fichero);
	//se asigana todo el contenido del archivo XML al atributo xml de la clase
	$c->setXML($strEntrada);
	//usando el formato F1, los valores del CFDI, para generar un nuevo renglón (uno por cada archivo)
	echo $c->getResumenF1Valores() . "\r\n";
	}
//Termina programa principal


/* Declaración de Clase para manejar los CFDIs */
// de declara clase en este mismo archivo por ser muy pequeña, y el programa que la usa también
class CFDI {
	//Atributos
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
	
	/* Constructor */
	function __construct($cadena = null){
		if(isset($cadena) && !is_null($cadena))
			$this->setXML($cadena);
	}
	/* Métodos */
	function setXML($cadena){
		//esta función asigna una cadena en formato XML (el contenido de un archivo CFDI)
		//al atributo xml de la clase, pero además obtiene los atributos del CFDI y los pone
		//en los campos correspondientes de la clase, los contenidos de esos campos pueden varios
		//dependiendo de la versión del documento.
		$this->xml = $cadena;
		$this->conceptos = "[";
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
						//la siguiente asignación se hace, si y sólo si, existe el atributo nombre
						//debería existir siempre, pero se encontraron casos en los que no se generó
						//para hacer más robusta esta clase, se debería entonces verificar todas las asignaciones
						//que vienen de la lectura del archivo
						$this->receptor_nombre = ( isset($arr_atributos['nombre']) ? $arr_atributos['nombre'] : '');
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
		//verificación de cantidades del CFDI, que cuadren cantidades, por las dudas
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
	function getXMLbonito(){
		//existen archivos CFDI que a veces no tienen saltos de línea y son complicado revisar
		//esta función genera un mismo formato XML, pero legible, utilizando librería tidy.
		$tidy_config = array( 
			'clean' => true, 
			'input-xml' => true, 
			'output-xml' => true,
			'preserve-entities' => true,
			'indent' => true,
			'indent-attributes' => true,
			'indent-spaces' => 8,
			'sort-attributes' => 'alpha',
			'vertical-space' => true,
			'wrap' => 0); 
	
	$tidy = tidy_parse_string($this->xml, $tidy_config, 'UTF8'); 
	$tidy->cleanRepair(); 
	return $tidy; 
	}
	//Las siguientes funciones son para generar un formato específico F1
	//de los datos que debe contener una archivo CSV, para que pueda
	//enviarse un grupo de CFDIs en una lista, en un archivo de Excel al contador
	function getResumenF1Encabezado($separador = ";"){
		//Encabezado del formato F1
		if( isset($separador) && (strlen(trim($separador))>0) )
			$separa = $separador;
		else
			$separa = ";";
		//encabezado
		return "Fecha/Hora emisión{$separa}Serie{$separa}Folio{$separa}UUID{$separa}RFC Emisor{$separa}Nombre o Razón Social{$separa}Importe{$separa}IVA{$separa}Total{$separa}Conceptos{$separa}Ver.{$separa}Tipo{$separa}Comentarios{$separa}RFC Receptor{$separa}Nombre o Razón Social{$separa}Forma de Pago{$separa}Condiciones de Pago{$separa}Método de Pago";
	}
	function getResumenF1Valores($separador = ";"){
		//Valores del formato F1
		if( isset($separador) && (strlen(trim($separador))>0) )
			$separa = $separador;
		else
			$separa = ";";
		$this->verifica();
		return ""
			. $this->getFechaHora() . $separa
			. $this->getSerie() . $separa
			. $this->getFolio() . $separa
			. $this->getUUID() . $separa
			. $this->getEmisorRFC() . $separa
			. $this->getEmisorNombre() . $separa
			. $this->getImporte() . $separa
			. $this->getIVA() . $separa
			. $this->getTotal() . $separa
			. $this->getConceptos() . $separa
			. $this->getVersion() . $separa
			. $this->getTipoComprobante() . $separa
			. $this->getComentarios() . $separa
			. $this->getReceptorRFC() . $separa
			. $this->getReceptorNombre() . $separa
			. $this->getPagoForma() . $separa
			. $this->getPagoCondiciones() . $separa
			. $this->getPagoMetodo();
	}	

	/* funcion que recibe una cadena RFC y determina si el CFDI está relacionado con ese RFC */
	/* ya sea porque es el emisor, o porque es el receptor */
	function rfc_relacionado($rfc){
                if((strcasecmp($this->receptor_rfc,$rfc) && strcasecmp($this->emisor_rfc,$rfc)))
                        return false;
                else
                        return true;
        }	
	/*La siguientes son funciones de lectura de los atributos del CFDI */
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
	function getXML(){
                return $this->xml;
        }	
}
?>
