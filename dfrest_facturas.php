<?php
require_once dirname(__FILE__).'/class/base_classes.php';

/**
 * Clase para accesar a un recurso factura de la API dtefacil.
 * Esta clase incluye funciones para crear y anular facturas,
 * asi como acceder a conjuntos de facturas a modo de reportes estadisticos
 * @author gertfindel
 *
 */
class DF_REST_Facturas extends DF_REST_Wrapper_Base {

    /**
     * La ruta base de los recursos factura.
     * @var string
     * @access private
     */
    var $_facturas_base_route;

    /**
     * Constructor.
     * @param $api_key string Tu api key
     * @param $protocol string El protocolo a usar (http|https)
     * @param $debug_level int El nivel de debugging requerido DF_REST_LOG_NONE | DF_REST_LOG_ERROR | DF_REST_LOG_WARNING | DF_REST_LOG_VERBOSE
     * @param $host string El host de la API al que se le mandan los requests. 
     * @param $log 
     * @param $serialiser El serializador a usar / para dependency injection
     * @param $transport El transporte a usar / para dependency injection
     * @access public
     */
    function DF_REST_Facturas (
    $api_key,
    $protocol = 'https',
    $debug_level = DF_REST_LOG_NONE,
    $host = 'api.dtefacil.cl',
    $log = NULL,
    $serialiser = NULL,
    $transport = NULL) {
        	
        $this->DF_REST_Wrapper_Base($api_key, $protocol, $debug_level, $host, $log, $serialiser, $transport);
        //especificos como 
        //$this->set_algo($otro_paramentro)
    }

    /**
     * Setear algun parametro
     * @param $param
     * @access public
     */
    function set_algo($param) {
        $this->_s_facturas_base_route = $this->_base_route.'facturas/'.$param.'/';
    }
}


