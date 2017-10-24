<?php
/*
Plugin Name: Paycores for WooCommerce
Plugin URI: https://github.com/Paycores/
Description: Paycores for WooCommerce. Recibe pagos desde cualquier parte del mundo.
Version: 1.0.1
Author: Paycores.com
Author URI: https://paycores.com/
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

add_action('plugins_loaded', 'paycores_woocommerce_init', 0);

function paycores_woocommerce_init(){
	if(!class_exists('WC_Payment_Gateway')) return;

    if( isset($_GET['msg']) && !empty($_GET['msg']) ){
        add_action('the_content', 'showPaycoresMessage');
    }

    /**
     * Muestra los mesajes de Paycores
     *
     * @access public
     * @param $content
     * @return string
     */
    function showPaycoresMessage($content){
            return '<div class="'.htmlentities($_GET['type']).'">'.htmlentities(urldecode($_GET['msg'])).'</div>'.$content;
    }

    /**
	 * Paycores Gateway Class
     *
     * @access public
     * @param 
     * @return 
     */
	class WC_paycores extends WC_Payment_Gateway{
		
		public function __construct(){
			global $woocommerce;

			$this->id                   = 'paycores';
			$this->icon_default         = $this->get_country_icon();
			$this->method_title         = __('Paycores','paycores-woocommerce');
			$this->method_description   = __("Recibe pagos desde cualquier parte del mundo",'paycores-woocommerce');
			$this->has_fields           = false;
			$this->init_form_fields();
			$this->init_settings();
			$this->language             = get_bloginfo('language');
			$this->testmode             = $this->settings['testmode'];
			$this->debug                = "no";
			$this->show_methods         = $this->settings['show_methods'];
			$this->icon_checkout        = $this->settings['icon_checkout'];

			if($this->show_methods=='yes'&&trim($this->settings['icon_checkout'])=='') {
				$this->icon =  $this->icon_default;
			}elseif(trim($this->settings['icon_checkout'])!=''){
				$this->icon = $this->settings['icon_checkout'];
			}else{
				$this->icon = $this->get_country_icon();
			}

			$this->title                = $this->settings['title'];
			$this->description          = $this->settings['description'];
			$this->commerceId           = $this->settings['commerceId'];
			$this->apiKeySecure         = $this->settings['apiKeySecure'];
			$this->apikey               = $this->settings['apikey'];
			$this->redirect_page_id     = $this->settings['redirect_page_id'];
			$this->taxes                = $this->settings['taxes'];
			$this->tax_return_base      = $this->settings['tax_return_base'];
			$this->currency             = ($this->is_valid_currency())?get_woocommerce_currency():'USD';
			$this->textactive           = 0;
			$this->form_method          = $this->settings['form_method'];
			$this->liveurl              = 'http://localhost/business_core/web-checkout/';//'https://business.paycores.com/web-checkout/';
			$this->testurl              = 'http://localhost/business_core/web-checkout/';//'https://sandbox.paycores.com/web-checkout/';
			/* Mensajes de Paycores */
			$this->msg_approved         = $this->settings['msg_approved'];
			$this->msg_declined         = $this->settings['msg_declined'];
			$this->msg_cancel           = $this->settings['msg_cancel'];
			$this->msg_pending          = $this->settings['msg_pending'];

			if ($this->testmode == "yes")
				$this->debug = "yes";

			add_filter( 'woocommerce_currencies', 'add_all_currency' );
			add_filter( 'woocommerce_currency_symbol', 'add_all_symbol', 10, 2);

			$this->msg['message'] 	= "";
			$this->msg['class'] 	= "";

			// Logs
            if(version_compare( WOOCOMMERCE_VERSION, '2.1', '>=')){
                $this->log = new WC_Logger();
            }else{
                $this->log = $woocommerce->logger();
            }
					
			add_action('paycores_init', array( $this, 'paycores_request'));
			add_action( 'woocommerce_receipt_paycores', array( $this, 'receipt_page' ) );
			//Actualizacion para woocommerce > 2.0
			add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'paycores_response' ) );
			
			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				/* 2.0.0 */
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
			} else {
				/* 1.6.6 */
				add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
			}
		}

        /**
         * Muestra el icono de Paycores
         *
         * @access public
         * @return string
         */
		public function get_country_icon(){
			$icon = "https://paycores.com/img/logo_16.png";
			return $icon;
		}

    	/**
		 * Verifica si se puede mostrar el checkout
	     *
	     * @access public
	     * @return void
	     */
	    function is_available() {
			global $woocommerce;

			if ( $this->enabled=="yes" ) :

				if ( !$this->is_valid_currency()) return false;

				if ( $woocommerce->version < '1.5.8' ) return false;

				if ($this->testmode!='yes'&&(!$this->commerceId || !$this->apiKeySecure || !$this->apikey )) return false;

				return true;
			endif;

			return false;
		}

    	/**
		 * Opciones de configuracion
	     *
	     * @access public
	     * @return void
	     */
		function init_form_fields(){
			$this->form_fields = array(
				'enabled' => array(
					'title' 		=> __('Activar/Desactivar', 'paycores-woocommerce'),
					'type' 			=> 'checkbox',
					'label' 		=> __('Habilitar el modulo de pagos de Paycores.', 'paycores-woocommerce'),
					'default' 		=> 'no',
					'description' 	=> __('Muestra a Paycores en la lista de pagos', 'paycores-woocommerce')
				),
				'show_methods' => array(
					'title' 		=> __('Mostrar Metodos', 'paycores-woocommerce'),
					'type' 			=> 'checkbox',
					'label' 		=> __('Mostrar metodos de pago por Pais.', 'paycores-woocommerce'),
					'default' 		=> 'no',
					'description' 	=> __('Mostrar imagen de los metodos de pago soportados por Pais.', 'paycores-woocommerce')
				),
      			'icon_checkout' => array(
					'title' 		=> __('Logo en el checkout:', 'paycores-woocommerce'),
					'type'			=> 'text',
					'default'		=> $this->get_country_icon(),
					'description' 	=> __('URL de la Imagen para mostrar en el carrro de compra.', 'paycores-woocommerce'),
					'desc_tip' 		=> true
				),
      			'title' => array(
					'title' 		=> __('Titulo:', 'paycores-woocommerce'),
					'type'			=> 'text',
					'default' 		=> __('Paycores', 'paycores-woocommerce'),
					'description' 	=> __('Este es el titulo que el usuario ve durante el pago', 'paycores-woocommerce'),
					'desc_tip' 		=> true
				),
      			'description' => array(
					'title' 		=> __('Descripción:', 'paycores-woocommerce'),
					'type' 			=> 'textarea',
					'default' 		=> __('En Paycores.com aceptamos todas las tarjetas de cŕédito, paga fácil, ágil y seguro.','paycores-woocommerce'),
					'description' 	=> __('Esta es la descripción que el usuario ve durante el pago.', 'paycores-woocommerce'),
					'desc_tip' 		=> true,
                    'css'           => 'width: 400px; !important;'
				),
      			'commerceId' => array(
					'title' 		=> __('CommerceID', 'paycores-woocommerce'),
					'type' 			=> 'text',
					'description' 	=> __('CommerceID asignado en el panel de administracion de Paycores.com', 'paycores-woocommerce'),
					'desc_tip' 		=> true
				),
                'apikey' => array(
                    'title' 		=> __('ApiKey', 'paycores-woocommerce'),
                    'type' 			=> 'text',
                    'description' 	=>  __('ApiKey asignado en el panel de administracion de Paycores.com', 'paycores-woocommerce'),
                    'desc_tip' 		=> true
                ),
      			'apiKeySecure' => array(
					'title' 		=> __('KeyLogin', 'paycores-woocommerce'),
					'type' 			=> 'text',
					'description' 	=> __('KeyLogin asignado en el panel de administracion de Paycores.com', 'paycores-woocommerce'),
					'desc_tip' 		=> true
				),
      			'testmode' => array(
					'title' 		=> __('Entorno de pruebas', 'paycores-woocommerce'),
					'type' 			=> 'checkbox',
					'label' 		=> __('Activa las transacciones de pruebas.', 'paycores-woocommerce'),
					'default' 		=> 'no',
					'description' 	=> __('Haga clic para activar el entorno de pruebas', 'paycores-woocommerce'),
					'desc_tip' 		=> true
                ),
                'taxes' => array(
					'title' 		=> __('Impuestos (IVA)', 'paycores-woocommerce').' <a target="_blank" href="https://paycores.com/">Paycores Documentacion</a>',
					'type' 			=> 'text',
					'default' 		=> '0.00',
					'description' 	=> __('impuestos por transacción (IVA).', 'paycores-woocommerce'),
					'desc_tip' 		=> true
		        ),
      			'tax_return_base' => array(
					'title' 		=> __('Retorno de impuestos (IVA)', 'paycores-woocommerce'),
					'type' 			=> 'text',
					'default' 		=> '0.00',
					'description' 	=> __('Base fiscal para calcular el IVA ', 'paycores-woocommerce'),
					'desc_tip' 		=> true
                ),
      			'form_method' => array(
					'title' 		=> __('Metodo del formulario', 'paycores-woocommerce'),
					'type' 			=> 'select',
					'default' 		=> 'POST',
					'options' 		=> array('POST' => 'POST', 'GET' => 'GET'),
					'description' 	=> __('Metodo de envio del formulario de pago ', 'paycores-woocommerce'),
					'desc_tip' 		=> true
                ),
      			'redirect_page_id' => array(
					'title' 		=> __('Pagina de retorno', 'paycores-woocommerce'),
					'type' 			=> 'select',
					'options' 		=> $this->get_pages(__('Select Page', 'paycores-woocommerce')),
					'description' 	=> __('URL de la pagina de respuesta satisfactoria', 'paycores-woocommerce'),
					'desc_tip' 		=> true
                ),
      			'msg_approved' => array(
					'title' 		=> __('Mensaje por pago aprobado', 'paycores-woocommerce'),
					'type' 			=> 'text',
					'default' 		=> __('Pago aprobado por Paycores.', 'paycores-woocommerce'),
					'description' 	=> __('Mensaje que se muestra cuando el pago es aprobado', 'paycores-woocommerce'),
					'desc_tip' 		=> true
                ),
      			'msg_pending' => array(
					'title' 		=> __('Mensaje para las transacciones pendientes', 'paycores-woocommerce'),
					'type' 			=> 'text',
					'default' 		=> __('Pago pendiente.', 'paycores-woocommerce'),
					'description' 	=> __('Mensaje que se muestra cuando esta pendiente la transacción', 'paycores-woocommerce'),
					'desc_tip' 		=> true
                ),
      			'msg_cancel' => array(
					'title' 		=> __('Mensaje para las transacciones canceladas', 'paycores-woocommerce'),
					'type' 			=> 'text',
					'default' 		=> __('Transacción cancelada.', 'paycores-woocommerce'),
					'description' 	=> __('Mensaje que se muestra cuando la transacción es cancelada', 'paycores-woocommerce'),
					'desc_tip' 		=> true
                ),
      			'msg_declined' => array(
					'title' 		=> __('Mensaje para las transacciones rechadas', 'paycores-woocommerce'),
					'type' 			=> 'text',
					'default' 		=> __('Pago rechazado por Paycores.', 'paycores-woocommerce'),
					'description' 	=> __('Mensaje que se muestra cuando la transacción es rechazada', 'paycores-woocommerce'),
					'desc_tip' 		=> true
                ),
			);
		}

        /**
         * Genera las opciones del panel de administrador
	     *
	     * @access public
	     * @return string
         **/
		public function admin_options(){
			echo '<img src="'.$this->get_country_icon().'" alt="Paycores" width="80"><h3>'.__('Paycores', 'paycores-woocommerce').'</h3>';
			echo '<p>'.__('Recibe pagos desde cualquier parte del mundo.', 'paycores-woocommerce').'</p>';
			echo '<table class="form-table">';
			// Genera el HTML para el formulario de configuracion.
			$this->generate_settings_html();
			echo '</table>';
		}

        /**
		 * Genera los campos de pago de Paycores
	     *
	     * @access public
	     * @return string
	     */
		function payment_fields(){
			if($this->description) echo wpautop(wptexturize($this->description));
		}

		/**
		 * Genera el formulario de paycores para el web-checkout
	     *
	     * @access public
	     * @param mixed $order
	     * @return string
		**/
		function receipt_page($order){
			echo '<p style="padding:10px;border-radius:5px;border:1px solid limegreen;">'.__('Gracias por su orden, por favor continue con el siguiente formulario y finalice su compra con Paycores.', 'paycores-woocommerce').'</p>';
			echo $this->generate_paycores_form($order);
		}

		/**
		 * Genera los argumentos POST de Paycores
	     *
	     * @access public
	     * @param mixed $order_id
	     * @return string
		**/
		function get_paycores_args($order_id){
			global $woocommerce;
			$order = new WC_Order( $order_id );

			$txnid = $order->order_key.'-'.time();

			$redirect_url = ($this->redirect_page_id=="" || $this->redirect_page_id==0)?get_site_url() . "/":get_permalink($this->redirect_page_id);
			//Para wooCoomerce 2.0
			$redirect_url = add_query_arg( 'wc-api', get_class( $this ), $redirect_url );
			$redirect_url = add_query_arg( 'order_id', $order_id, $redirect_url );

			$productinfo = "Order $order_id";
            $str = "$this->apikey~$this->commerceId~$txnid~$order->order_total~$this->currency";
            $hash = strtolower(md5($str));

            $productsPaycores = count($order->get_items());

			$paycores = "paycores";

			$paycores_args = array(
				$paycores.'_is_woocommerce'     => true,
				$paycores.'_access_commerceid'  => $this->commerceId,
				$paycores.'_access_login'       => $this->apiKeySecure,
				$paycores.'_signature'          => $hash,
				$paycores.'_referenceCode'      => $txnid,
				$paycores.'_amount'             => $order->order_total,
				$paycores.'_currency'           => $this->currency,
				$paycores.'_usr_name'           => $order->billing_first_name,
				$paycores.'_usr_lname'          => $order->billing_last_name,
				$paycores.'_usr_email'          => $order->billing_email,
				$paycores.'_usr_phone'          => $order->billing_phone,
				$paycores.'_usr_cellphone'      => $order->billing_phone,
				$paycores.'_usr_address'        => $order->billing_address_1.' '.$order->billing_address_2,
				$paycores.'_usr_city'           => $order->billing_city,
				$paycores.'_usr_state'          => $order->billing_state,
				$paycores.'_usr_country_ad'     => $order->billing_country,
				$paycores.'_usr_nation'         => $order->billing_country,
				$paycores.'_usr_postal_code'    => $order->billing_postcode,
				$paycores.'_description'        => $productinfo,
				$paycores.'_gd_name'            => $productinfo,
				$paycores.'_gd_descript'        => $productinfo,
				$paycores.'_gd_quantity'        => $productsPaycores,
				$paycores.'_gd_item'            => intval($order->order_total),
				$paycores.'_gd_code'            => intval($order->order_total),
				$paycores.'_gd_amount'          => intval($order->order_total),
				$paycores.'_gd_unitPrice'       => $order->order_total,
				$paycores.'_tax'                => $this->taxes,
				$paycores.'_tax_ret'            => $this->tax_return_base,
				$paycores.'_extra1'             => $order->order_id,
				$paycores.'_response_url'       => $redirect_url,
				$paycores.'_confirmation_url'   => $redirect_url
			);

			if ( $this->testmode == 'yes' ){
				$paycores_args[$paycores.'_access_key']     = $this->apikey;
				$paycores_args[$paycores.'_test'] = '1';
			}else{
				$paycores_args[$paycores.'_access_key']     = $this->apikey;
				$paycores_args[$paycores.'_test'] = '2';
			}

			return $paycores_args;
		}

		/**
         * Crea la redireccion del formulario de Paycores
	     *
	     * @access public
	     * @param mixed $order_id
	     * @return string
	    */
	    function generate_paycores_form( $order_id ) {
			global $woocommerce;

			$order = new WC_Order( $order_id );

			if ( $this->testmode == 'yes' )
				$paycores_adr = $this->testurl;
			else 
				$paycores_adr = $this->liveurl;

			$paycores_args = $this->get_paycores_args( $order_id );
			$paycores_args_array = array();

			foreach ($paycores_args as $key => $value) {
				$paycores_args_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
			}

			return '<form action="'.$paycores_adr.'" method="POST" id="paycores_payment_form" target="_top">
					' . implode( '', $paycores_args_array) . '
                     <style>
					    .input-pay {
					    float:none !important;width:200px !important;margin-bottom:20px !important;padding:5px !important;border-radius:5px !important;border:0.4px solid #CDCDCD !important;background:#fff !important;
					    }
					    .col-pay-12 {width:100%;float:left;}
					    .col-pay-6 {width:41%;float:left;}
                    </style>
					  <div class="col-pay-12">
                          <div class="col-pay-6">
                            <label for="genlist">Género</label> 
                          </div>
                          <div class="col-pay-6">
                            <select name="paycores_usr_gender" id="genlist" class="input-pay">
                                <option value="M">Masculino</option>
                                <option value="F">Femenino</option>
                            </select>
                            <div id="messageGener" class="font-red"></div>
                          </div>
					  </div>
					  
					  <div class="col-pay-12">
                          <div class="col-pay-6">
                            <label for="paycores_usr_birth">Fecha de nacimiento</label>
                          </div>
                          <div class="col-pay-6">
                            <input type="text" class="input-pay" name="paycores_usr_birth"     id="paycores_usr_birth" placeholder="1980-01-20">
                          </div>
					  </div>
					  
					  <div class="col-pay-12">
                          <div class="col-pay-6">
                            <label for="paycores_usr_numberId">Número de identificación</label>
                          </div>
                          <div class="col-pay-6">
                            <input type="text" class="input-pay" name="paycores_usr_numberId"  id="paycores_usr_numberId" placeholder="1094000000">
                          </div>
					  </div>
					  
					  <input type="submit" class="button alt" id="submit_paycores_payment_form" value="' . __( 'Pago a través de Paycores', 'paycores-woocommerce' ) . '" /> <a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__( 'Cancel order &amp; restore cart', 'woocommerce' ).'</a>
				    </form>';
		}

		/**
	     * Procesa el pago y retorna la respuesta
	     *
	     * @access public
	     * @param int $order_id
	     * @return array
	     */
		function process_payment( $order_id ) {
			$order = new WC_Order( $order_id );
			if ( $this->form_method == 'GET' ) {
				$paycores_args = $this->get_paycores_args( $order_id );
				$paycores_args = http_build_query( $paycores_args, '', '&' );
				if ( $this->testmode == 'yes' ):
					$paycores_adr = $this->testurl . '&';
				else :
					$paycores_adr = $this->liveurl . '?';
				endif;

				return array(
					'result' 	=> 'success',
					'redirect'	=> $paycores_adr . $paycores_args
				);
			} else {
				if (version_compare( WOOCOMMERCE_VERSION, '2.1', '>=')) {
					return array(
						'result' 	=> 'success',
						'redirect'	=> add_query_arg('order-pay', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay' ))))
					);
				} else {
					return array(
						'result' 	=> 'success',
						'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay' ))))
					);
				}
			}
		}

        /**
         * Verifica si el servidor se encuentra en servicio
         *
         * @access public
         * @return void
         */
		function paycores_response(){
			@ob_clean();
	    	if ( ! empty( $_REQUEST ) ) {
	    		header( 'HTTP/1.1 200 OK' );
	        	do_action( "paycores_init", $_REQUEST );
			} else {
				wp_die( __("peticion de Paycores fracaso", 'paycores-woocommerce') );
	   		}
		}

		/**
		 * Procesa la informacion de Paycores y actualiza la informacion de la orden
		 *
		 * @access public
		 * @param array $posted
		 * @return void
		 */
		function paycores_request( $posted ) {
			global $woocommerce;

			if ( ! empty( $posted['message'] ) && ! empty( $posted['codeResponse'] ) ) {
				$this->paycores_confirmation($posted);
			} else {
                $redirect_url = $woocommerce->cart->get_checkout_url();
                //For wooCoomerce 2.0
                $redirect_url = add_query_arg( array('msg'=> urlencode(__( 'Hubo un error con la solicitud, cominiquese con el administrador del sitio web', 'paycores' )), 'type'=>$this->msg['class']), $redirect_url );

                wp_redirect( $redirect_url );
            }
            exit;
		}

        /**
         * Procesar pagina de confirmacion
         *
         * @param $posted
         */
		function paycores_confirmation($posted){
			global $woocommerce;

            $order = $this->get_paycores_order( $posted );

            $codes=array(
                '001'     => 'APPROVED'
            );

            if ( 'yes' == $this->debug )
                $this->log->add( 'paycores', 'oden encontrada #' . $order->id );

            $state=$posted['codeResponse'];

            if ( 'yes' == $this->debug )
                $this->log->add( 'paycores', 'estado de pago: ' . $codes[$state] );

            // Se verifica el estado de repuesta de Paycores
            switch ( $state ) {
                case '001':
                    $this->msg['message'] =  $this->msg_approved;
                    $this->msg['class'] = 'woocommerce-message';
                    $order->add_order_note( __( 'Pago aprobado por Paycores', 'paycores-woocommerce') );
                    $order->update_status( 'completed' );
                    $order->payment_complete();
                    $order->reduce_order_stock();
                    $woocommerce->cart->empty_cart();
                    if ( 'yes' == $this->debug ){ $this->log->add( 'paycores', __('Pago completo.', 'paycores-woocommerce'));}

                    $shop_page_url = $woocommerce->cart->get_cart_url();
                    $redirect_url = add_query_arg( array('msg'=> urlencode(__( 'Pago aprobado por Paycores', 'paycores' )), 'type'=>$this->msg['class']), $shop_page_url );
                    wp_redirect($redirect_url);
                    break;
                default :
                    $this->msg['message'] = $this->msg_cancel ;
                    $this->msg['class'] = 'woocommerce-error';
                    $order->update_status( 'failed', sprintf( __( 'Pago rechazado por Paycores.', 'paycores-woocommerce'), ( $codes[$state] ) ) );
                    $order->update_status( 'Failed' );
                    $woocommerce->cart->empty_cart();

                    $shop_page_url = $woocommerce->cart->get_cart_url();
                    $redirect_url = add_query_arg( array('msg'=> urlencode(__( 'Pago rechazado por Paycores.', 'paycores' )), 'type'=>$this->msg['class']), $shop_page_url );
                    wp_redirect( $redirect_url );
                    break;
            }
            exit;
		}

		/**
         * Obtiene la informacion de la orden
		 *
		 * @access public
		 * @param mixed $posted
		 * @return void
		 */
		function get_paycores_order( $posted ) {
			$custom =  $posted['order_id'];

            $order_id = (int) $custom;
			$order = new WC_Order( $order_id );

	        return $order;
		}

		/**
		 * verifica si el tipo de moneda es valido para Paycores
		 *
		 * @access public
		 * @return bool
		 */
		function is_valid_currency() {
			if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_paycores_supported_currencies', array( 'COP', 'USD' ) ) ) ) return false;

			return true;
		}

		/**
         * Obitne las paginas de respuesta
		 *
		 * @access public
         * @param $title
         * @param $indent
		 * @return bool
		 */
		function get_pages($title = false, $indent = true) {
			$wp_pages = get_pages('sort_column=menu_order');
			$page_list = array('default'=>__('Default Page','paycores-woocommerce'));
			if ($title) $page_list[] = $title;
			foreach ($wp_pages as $page) {
				$prefix = '';
				// Muestra la sangria de las paginas
				if ($indent) {
                	$has_parent = $page->post_parent;
                	while($has_parent) {
                    	$prefix .=  ' - ';
                    	$next_page = get_page($has_parent);
                    	$has_parent = $next_page->post_parent;
                	}
            	}
                // Agrega la pagina al array
            	$page_list[$page->ID] = $prefix . $page->post_title;
        	}
        	return $page_list;
        }
    }


    /**
     * Agrega todos los tipos de moneda soportados por Paycores
     * Y los muestra en los ajustes de WooCommerce
     *
     * @access public
     * @param $currencies
     * @return bool
     */
    function add_all_currency( $currencies ) {
        $currencies['USD'] = __( 'Dólar estadounidense', 'paycores-woocommerce');
        $currencies['COP'] = __( 'Peso Colombiano', 'paycores-woocommerce');
        return $currencies;
    }

    /**
     * Agrega los simbolos de todos los tipos de modeda de Paycores
     * y los muestra en los ajustes de WooCommerce
     *
     * @access public
     * @param $currency_symbol
     * @param $currency
     * @return bool
     */
    function add_all_symbol( $currency_symbol, $currency ) {
        switch( $currency ) {
            case 'USD': $currency_symbol = '$'; break;
            case 'COP': $currency_symbol = '$'; break;
        }
        return $currency_symbol;
    }
    /**
    * Agrega Paycores Gateway a WooCommerce
    **/
    function woocommerce_add_paycores($methods) {
        $methods[] = 'WC_paycores';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_paycores' );
}

/**
 * Filtro de simbolos por los tipos de moneda
 *
 * @access public
 * @param (string) $currency_symbol, (string) $currency
 * @return (string) filtered currency simbol
 */
function frontend_filter_currency_symbol( $currency_symbol, $currency ) {
    switch( $currency ) {
        case 'USD': $currency_symbol = '$'; break;
        case 'COP': $currency_symbol = '$'; break;
    }
    return $currency_symbol;
}

add_filter( 'woocommerce_currency_symbol', 'frontend_filter_currency_symbol', 1, 2);