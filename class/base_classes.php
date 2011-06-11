<?php

//require_once dirname(__FILE__).'/serialisation.php';
//require_once dirname(__FILE__).'/transport.php';
//require_once dirname(__FILE__).'/log.php';

define('DF_REST_WRAPPER_VERSION', '0.0.1');

define('DF_REST_WEBHOOK_FORMAT_JSON', 'json');
define('DF_REST_WEBHOOK_FORMAT_XML', 'xml');

/**
 * Un objeto generico devuelto por la API de dtefacil.
 * @author gertfindel
 *
 */
class DF_REST_Wrapper_Result {
    /**
     * El resultado deserializado de una llamada a la API
     * @var mixed
     */
    var $response;
    
    /**
     * El codigo de status de la llamada a la API
     * @var int
     */
    var $http_status_code;
    
    function DF_REST_Wrapper_Result($response, $code) {
        $this->response = $response;
        $this->http_status_code = $code;
    }

    /**
     * Puede ser usado para verificar que un llamado a la API resulto exitoso.
     * @return boolean False si falla.
     * @access public
     */
    function was_successful() {
        return $this->http_status_code >= 200 && $this->http_status_code < 300;
    }
}

/**
 * Clase base para los llamados a dtefacil.
 * Esta clase incluye funciones para el acceso general a data,
 * @author gertfindel 
 *
 */
class DF_REST_Wrapper_Base {
    /**
     * El protocolo a usar para acceder a la API
     * @var string http o https
     * @access private
     */
    var $_protocol;

    /**
     * La ruta base a la API de dtefacil
     * @var string
     * @access private
     */
    var $_base_route;

    /**
     * @var DF_REST_JsonSerialiser or DF_REST_XmlSerialiser
     * @access private
     */
    var $_serialiser;

    /**
     * @var DF_REST_CurlTransport or DF_REST_SocketTransport o un transporte a la medida.
     * @access private
     */
    var $_transport;

    /**
     * @var DF_REST_Log
     * @access private
     */
    var $_log;

    /**
     * Las opciones por defecto a usar para todos los llamados a la API.
     * Estos pueden ser sobrecargados pasando un arreglo en el parametro call_options a un llamado en particular
     * Opciones validas son:
     *
     * deserialise boolean:
     *     Setear en false para recibir la respuesta raw.
     *
     * @var array
     * @access private
     */
    var $_default_call_options;

    /**
     * Constructor.
     * @param $api_key string Tu api key
     * @param $protocol string El protocolo a usar para los llamados (http|https)
     * @param $debug_level int El nivel de debugging requerido DF_REST_LOG_NONE | DF_REST_LOG_ERROR | DF_REST_LOG_WARNING | DF_REST_LOG_VERBOSE
     * @param $host string El host de la API al que se hacen los llamados.
     * @param $log DF_REST_Log Logger para dependency injection
     * @param $serialiser serializador para dependency injection
     * @param $transport transporte para dependency injection
     * @access public
     */
    function DF_REST_Wrapper_Base(
        $api_key,
        $protocol = 'https',
        $debug_level = DF_REST_LOG_NONE,
        $host = 'api.dtefacil.cl',
        $log = NULL,
        $serialiser = NULL,
        $transport = NULL) {
            
        $this->_log = is_null($log) ? new DF_REST_Log($debug_level) : $log;
            
        $this->_protocol = $protocol;
        $this->_base_route = $protocol.'://'.$host.'/v1/';

        $this->_log->log_message('Creando wrapper para '.$this->_base_route, get_class($this), DF_REST_LOG_VERBOSE);

        $this->_transport = is_null($transport) ?
        @DF_REST_TransportFactory::get_available_transport($this->is_secure(), $this->_log) :
        $transport;

        $transport_type = method_exists($this->_transport, 'get_type') ? $this->_transport->get_type() : 'Unknown';
        $this->_log->log_message('Using '.$transport_type.' for transport', get_class($this), DF_REST_LOG_WARNING);

        $this->_serialiser = is_null($serialiser) ?
            @DF_REST_SerialiserFactory::get_available_serialiser($this->_log) : $serialiser;
            
        $this->_log->log_message('Usando '.$this->_serialiser->get_type().' json serialising', get_class($this), DF_REST_LOG_WARNING);

        $this->_default_call_options = array (
            'credentials' => $api_key.':nopass',
            'userAgent' => 'DF_REST_Wrapper v'.DF_REST_WRAPPER_VERSION.
                ' PHPv'.phpversion().' over '.$transport_type.' with '.$this->_serialiser->get_type(),
            'contentType' => 'application/xml; charset=utf-8', 
            'deserialise' => true,
            'host' => $host,
            'protocol' => $protocol
        );
    }

    /**
     * @return boolean True si se usa SSL.
     * @access public
     */
    function is_secure() {
        return $this->_protocol === 'https';
    }
    
    function put_request($route, $data, $call_options = array()) {
        return $this->_call($call_options, DF_REST_PUT, $route, $data);
    }
    
    function post_request($route, $data, $call_options = array()) {
        return $this->_call($call_options, DF_REST_POST, $route, $data);
    }
    
    function delete_request($route, $call_options = array()) {
        return $this->_call($call_options, DF_REST_DELETE, $route);
    }
    
    function get_request($route, $call_options = array()) {
        return $this->_call($call_options, DF_REST_GET, $route);
    }
    
    function get_request_paged($route, $page_number, $page_size, $order_field, $order_direction,
        $join_char = '&') {      
        if(!is_null($page_number)) {
            $route .= $join_char.'page='.$page_number;
            $join_char = '&';
        }
        
        if(!is_null($page_size)) {
            $route .= $join_char.'pageSize='.$page_size;
            $join_char = '&';
        }
        
        if(!is_null($order_field)) {
            $route .= $join_char.'orderField='.$order_field;
            $join_char = '&';
        }
        
        if(!is_null($order_direction)) {
            $route .= $join_char.'orderDirection='.$order_direction;
            $join_char = '&';
        }
        
        return $this->get_request($route);      
    }       

    /**
     * Internal method to make a general API request based on the provided options
     * @param $call_options
     * @access private
     */
    function _call($call_options, $method, $route, $data = NULL) {
        $call_options['route'] = $route;
        $call_options['method'] = $method;
        
        if(!is_null($data)) {
            $call_options['data'] = $this->_serialiser->serialise($data);
        }
        
        $call_options = array_merge($this->_default_call_options, $call_options);
        $this->_log->log_message('Making '.$call_options['method'].' call to: '.$call_options['route'], get_class($this), DF_REST_LOG_WARNING);
            
        $call_result = $this->_transport->make_call($call_options);

        $this->_log->log_message('Call result: <pre>'.var_export($call_result, true).'</pre>',
            get_class($this), DF_REST_LOG_VERBOSE);

        if($call_options['deserialise']) {
            $call_result['response'] = $this->_serialiser->deserialise($call_result['response']);
        }
         
        return new DF_REST_Wrapper_Result($call_result['response'], $call_result['code']);
    }
}
