<?php
/*
Plugin Name: Cookie Creator
Plugin URI:  https://webdesignerk.com
Description: Crea cookies personalizadas basadas en parámetros UTM, visitas a páginas, clics, login de usuario, tiempo en página, envíos de CF7 y Session ID. Normaliza UTMs (ñ→n, +/espacios→_), y ofrece envolver el valor en SHA-256.
Version:     1.2.3
Author:      Konstantin WDk
License:     GPL v2 or later
Text Domain: cookie-creator
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Cookie_Creator' ) ) {
    final class Cookie_Creator {
        const CPT     = 'cookie_creator';
        const VERSION = '1.2.3';
        private static $instance = null;

        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        private function __clone() {}
        private function __wakeup() {}

        public function __construct() {
            // Backend
            add_action( 'init',                  [ $this, 'register_cpt' ] );
            add_action( 'add_meta_boxes',        [ $this, 'register_meta_boxes' ] );
            add_action( 'save_post',             [ $this, 'save_meta' ] );
            add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
            // Frontend
            add_action( 'wp_enqueue_scripts',    [ $this, 'frontend_assets' ] );
            add_action( 'wp_footer',             [ $this, 'inline_js' ], 100 );
            // Login trigger
            add_action( 'wp_login',              [ $this, 'set_login_cookie' ], 10, 2 );
        }

        public function register_cpt() {
            $labels = [
                'name'          => __( 'Cookies', 'cookie-creator' ),
                'singular_name' => __( 'Cookie', 'cookie-creator' ),
                'add_new_item'  => __( 'Añadir nueva cookie', 'cookie-creator' ),
                'edit_item'     => __( 'Editar cookie', 'cookie-creator' ),
                'menu_name'     => __( 'Cookie Creator', 'cookie-creator' ),
            ];
            register_post_type( self::CPT, [
                'labels'    => $labels,
                'public'    => false,
                'show_ui'   => true,
                'menu_icon' => 'dashicons-editor-code',
                'supports'  => [ 'title' ],
            ] );
        }

        public function register_meta_boxes() {
            add_meta_box(
                'cc_settings',
                __( 'Configuración de la cookie', 'cookie-creator' ),
                [ $this, 'render_settings_metabox' ],
                self::CPT, 'normal', 'default'
            );
            add_meta_box(
                'cc_help',
                __( 'Ayuda & ejemplos', 'cookie-creator' ),
                [ $this, 'render_help_metabox' ],
                self::CPT, 'side', 'low'
            );
        }

        public function render_settings_metabox( $post ) {
            wp_nonce_field( 'cc_save_meta', 'cc_nonce' );
            $meta = get_post_meta( $post->ID );
            $get  = function( $key, $def = '' ) use ( $meta ) {
                return isset( $meta[ $key ][0] ) ? esc_attr( $meta[ $key ][0] ) : $def;
            };

            $trigger      = $get( 'cc_trigger', 'utm' );
            $name         = $get( 'cc_name', '' );
            $value        = $get( 'cc_value', '' );
            $duration     = $get( 'cc_duration', '30' );
            $page_id      = $get( 'cc_page_id', '' );
            $custom_url   = $get( 'cc_custom_url', '' );
            $click_id     = $get( 'cc_click_id', '' );
            $time_sec     = $get( 'cc_time_seconds', '' );
            $time_min     = $get( 'cc_time_minutes', '' );
            $hash         = $get( 'cc_hash', '' );
            ?>
            <table class="form-table"><tbody>
                <tr>
                    <th><label for="cc_trigger"><?php _e( 'Tipo de trigger', 'cookie-creator' ); ?></label></th>
                    <td>
                        <select name="cc_trigger" id="cc_trigger">
                            <option value="utm"       <?php selected( $trigger, 'utm' );       ?>>UTM</option>
                            <option value="page_visit"<?php selected( $trigger, 'page_visit' );?>>Visita de página</option>
                            <option value="click"     <?php selected( $trigger, 'click' );     ?>>Clic en #ID</option>
                            <option value="login"     <?php selected( $trigger, 'login' );     ?>>Login de usuario</option>
                            <option value="cf7"       <?php selected( $trigger, 'cf7' );       ?>>Envío CF7</option>
                            <option value="time"      <?php selected( $trigger, 'time' );      ?>>Tiempo en página</option>
                            <option value="session"   <?php selected( $trigger, 'session' );   ?>>Session ID</option>
                        </select>
                    </td>
                </tr>
                <tr class="cc-row cc-name">
                    <th><label for="cc_name"><?php _e( 'Nombre de la cookie', 'cookie-creator' ); ?></label></th>
                    <td><input type="text" name="cc_name" id="cc_name" value="<?php echo $name; ?>" class="regular-text" required></td>
                </tr>
                <tr class="cc-row cc-value">
                    <th><label for="cc_value"><?php _e( 'Valor por defecto', 'cookie-creator' ); ?></label></th>
                    <td><input type="text" name="cc_value" id="cc_value" value="<?php echo $value; ?>" class="regular-text"></td>
                </tr>
                <tr class="cc-row cc-duration">
                    <th><label for="cc_duration"><?php _e( 'Duración (días)', 'cookie-creator' ); ?></label></th>
                    <td><input type="number" min="1" name="cc_duration" id="cc_duration" value="<?php echo $duration; ?>" class="small-text"></td>
                </tr>
                <tr class="cc-row cc-hash">
                    <th><label for="cc_hash"><?php _e( 'Envolver valor en SHA256', 'cookie-creator' ); ?></label></th>
                    <td><input type="checkbox" name="cc_hash" id="cc_hash" value="1" <?php checked( $hash, '1' ); ?>></td>
                </tr>
                <tr class="cc-row cc-page" <?php if ( $trigger!=='page_visit') echo 'style="display:none"';?>>
                    <th><label for="cc_page_id"><?php _e( 'Página a vigilar', 'cookie-creator' ); ?></label></th>
                    <td>
                        <?php wp_dropdown_pages([
                            'name'=>'cc_page_id','show_option_none'=>__( '— Selecciona —','cookie-creator'),
                            'option_none_value'=>'','selected'=>$page_id,
                        ]); ?>
                        <p class="description"><?php _e('O usa URL personalizada:','cookie-creator');?></p>
                        <input type="url" name="cc_custom_url" value="<?php echo $custom_url;?>" class="regular-text">
                    </td>
                </tr>
                <tr class="cc-row cc-click" <?php if ( $trigger!=='click') echo 'style="display:none"';?>>
                    <th><label for="cc_click_id"><?php _e('ID del elemento','cookie-creator');?></label></th>
                    <td><input type="text" name="cc_click_id" value="<?php echo $click_id;?>" class="regular-text"></td>
                </tr>
                <tr class="cc-row cc-time" <?php if ( $trigger!=='time') echo 'style="display:none"';?>>
                    <th><?php _e('Tiempo en página','cookie-creator');?></th>
                    <td>
                        <input type="number" min="1" name="cc_time_seconds" value="<?php echo $time_sec;?>" class="small-text"> <?php _e('s','cookie-creator');?><br>
                        <input type="number" min="1" name="cc_time_minutes" value="<?php echo $time_min;?>" class="small-text"> <?php _e('min','cookie-creator');?>
                    </td>
                </tr>
            </tbody></table>

            <!-- Render información del trigger -->
            <div id="cc_trigger_info_content" style="border:1px solid #ddd;padding:10px;margin-top:15px;background:#f9f9f9;">
                <div id="cc_trigger_info_utm" class="cc-trigger-info-box">
                    <h4><?php _e( 'Trigger UTM', 'cookie-creator' );?></h4>
                    <p><?php _e('Crea cookie por cada UTM detectado; normaliza: ñ→n, espacios/+→_','cookie-creator');?></p>
                </div>
                <div id="cc_trigger_info_page_visit" class="cc-trigger-info-box" style="display:none">
                    <h4><?php _e('Visita de Página','cookie-creator');?></h4>
                    <p><?php _e('Se dispara al visitar la página/URL configurada.','cookie-creator');?></p>
                </div>
                <div id="cc_trigger_info_click" class="cc-trigger-info-box" style="display:none">
                    <h4><?php _e('Clic en Elemento','cookie-creator');?></h4>
                    <p><?php _e('Se dispara al clicar en el selector indicado.','cookie-creator');?></p>
                </div>
                <div id="cc_trigger_info_login" class="cc-trigger-info-box" style="display:none">
                    <h4><?php _e('Login de Usuario','cookie-creator');?></h4>
                    <p><?php _e('Se dispara al iniciar sesión en WP.','cookie-creator');?></p>
                </div>
                <div id="cc_trigger_info_cf7" class="cc-trigger-info-box" style="display:none">
                    <h4><?php _e('Contact Form 7','cookie-creator');?></h4>
                    <p><?php _e('Se dispara al enviar un CF7 exitoso.','cookie-creator');?></p>
                </div>
                <div id="cc_trigger_info_time" class="cc-trigger-info-box" style="display:none">
                    <h4><?php _e('Tiempo en Página','cookie-creator');?></h4>
                    <p><?php _e('Se dispara tras el tiempo especificado.','cookie-creator');?></p>
                </div>
                <div id="cc_trigger_info_session" class="cc-trigger-info-box" style="display:none">
                    <h4><?php _e('Session ID','cookie-creator');?></h4>
                    <p><?php _e('Genera ID aleatorio 12-caracteres y lo guarda.','cookie-creator');?></p>
                </div>
            </div>

            <script>
            (function($){
                function toggleRows(){
                    var t=$('#cc_trigger').val();
                    $('.cc-row').hide();
                    $('.cc-name, .cc-duration, .cc-hash').show();
                    $('.cc-value').show();
                    if(t==='page_visit') $('.cc-page').show();
                    if(t==='click')      $('.cc-click').show();
                    if(t==='time')       $('.cc-time').show();
                    if(t==='session')    $('.cc-value').hide();
                    $('.cc-trigger-info-box').hide();
                    $('#cc_trigger_info_'+t).show();
                }
                $(document).on('change','#cc_trigger',toggleRows);
                $(document).ready(toggleRows);
            })(jQuery);
            </script>
            <?php
        }

        public function render_help_metabox() {
            echo '<p><strong>'.__( 'Uso en PHP/JS:','cookie-creator').'</strong></p>';
            echo '<p>PHP: <code>$_COOKIE["nombre"]</code><br>JS: <code>document.cookie</code></p>';
        }

        public function save_meta( $post_id ) {
            if( ! isset($_POST['cc_nonce'])||!wp_verify_nonce($_POST['cc_nonce'],'cc_save_meta') )return;
            if( defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE )return;
            if( self::CPT!==get_post_type($post_id) )return;
            if( ! current_user_can('edit_post',$post_id) )return;
            $fields=[ 'cc_trigger','cc_name','cc_value','cc_duration',
                      'cc_page_id','cc_custom_url','cc_click_id',
                      'cc_time_seconds','cc_time_minutes','cc_hash' ];
            foreach($fields as $f){
                $v= isset($_POST[$f])? sanitize_text_field(wp_unslash($_POST[$f])) : '';
                update_post_meta($post_id,$f,$v);
            }
        }

        public function admin_assets($hook){
            if(!in_array($hook,['post.php','post-new.php'],true))return;
            if(self::CPT!==get_post_type())return;
            wp_enqueue_script('jquery');
        }

        public function frontend_assets(){
            wp_register_script('cc-frontend','',[],self::VERSION,true);
            $cfg=[]; $posts=get_posts([
                'post_type'=>self::CPT,'post_status'=>'publish','numberposts'=>-1
            ]);
            foreach($posts as $p){
                $m=get_post_meta($p->ID);
                $ms= (!empty($m['cc_time_seconds'][0])? intval($m['cc_time_seconds'][0])*1000:
                     (!empty($m['cc_time_minutes'][0])? intval($m['cc_time_minutes'][0])*60000:0));
                $cfg[]= [
                    'trigger'=> $m['cc_trigger'][0] ?? 'utm',
                    'name'   => $m['cc_name'][0]    ?? '',
                    'value'  => $m['cc_value'][0]   ?? '',
                    'duration'=>intval($m['cc_duration'][0] ?? 30),
                    'pageId' =>intval($m['cc_page_id'][0] ?? 0),
                    'customUrl'=>$m['cc_custom_url'][0] ?? '',
                    'clickId'=>$m['cc_click_id'][0] ?? '',
                    'timeMs'=>$ms,
                    'hash'=>!empty($m['cc_hash'][0]),
                ];
            }
            wp_localize_script('cc-frontend','ccCookieConfigs',['items'=>$cfg]);
            wp_enqueue_script('cc-frontend');
        }

        public function inline_js(){
            if(!wp_script_is('cc-frontend','enqueued'))return;
            ?>
            <script id="cc-cookie-creator-inline-js">
            (function(cfgs){
                if(!cfgs||!cfgs.length)return;
                function rnd(len){
                    var s='',chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                    for(var i=0;i<len;i++)s+=chars[Math.floor(Math.random()*chars.length)];
                    return s;
                }
                function setCookie(n,v,d){
                    var e=new Date();e.setTime(e.getTime()+d*86400000);
                    document.cookie=n+'='+encodeURIComponent(v)+';expires='+e.toUTCString()+';path=/';
                }
                var params=new URLSearchParams(window.location.search),
                    path=window.location.pathname.replace(/\/$/,'');
                cfgs.forEach(function(c){
                    switch(c.trigger){
                        case 'utm':
                            ['utm_id','utm_source','utm_medium','utm_campaign','utm_term','utm_content']
                            .forEach(function(k){
                                if(!params.has(k))return;
                                var raw=params.get(k),
                                    norm=raw.trim().toLowerCase()
                                        .replace(/ñ/g,'n')
                                        .replace(/[\s\+]+/g,'_'),
                                    name=c.name+'_'+k;
                                if(c.hash){
                                    crypto.subtle.digest('SHA-256',new TextEncoder().encode(norm))
                                    .then(function(buf){
                                        var h=Array.from(new Uint8Array(buf))
                                            .map(b=>b.toString(16).padStart(2,'0')).join('');
                                        setCookie(name,h,c.duration);
                                    });
                                } else setCookie(name,norm,c.duration);
                            });
                            break;
                        case 'page_visit':
                            var tgt=c.customUrl? new URL(c.customUrl,location.origin).pathname.replace(/\/$/,''): '';
                            if((c.pageId&&document.body.classList.contains('page-id-'+c.pageId))
                               ||(tgt&&tgt===path)) setCookie(c.name,c.value,c.duration);
                            break;
                        case 'click':
                            if(!c.clickId)return;
                            document.addEventListener('click',function(e){
                                if(e.target.closest(c.clickId))
                                    setCookie(c.name,c.value,c.duration);
                            });
                            break;
                        case 'cf7':
                            document.addEventListener('wpcf7mailsent',function(){
                                setCookie(c.name,c.value,c.duration);
                            });
                            break;
                        case 'time':
                            if(!c.timeMs)return;
                            setTimeout(function(){
                                setCookie(c.name,c.value,c.duration);
                            },c.timeMs);
                            break;
                        case 'session':
                            var sid=rnd(12);
                            if(c.hash){
                                crypto.subtle.digest('SHA-256',new TextEncoder().encode(sid))
                                .then(function(buf){
                                    var h=Array.from(new Uint8Array(buf))
                                        .map(b=>b.toString(16).padStart(2,'0')).join('');
                                    setCookie(c.name,h,c.duration);
                                });
                            } else setCookie(c.name,sid,c.duration);
                            break;
                    }
                });
            })(window.ccCookieConfigs.items);
            </script>
            <?php
        }

        public function set_login_cookie($login,$user){
            foreach($this->get_login_cookies() as $c){
                setcookie($c['name'],$c['value'],time()+($c['duration']*DAY_IN_SECONDS),
                    COOKIEPATH,'',false,true);
            }
        }
        private function get_login_cookies(){
            $out=[]; $posts=get_posts([
                'post_type'=>self::CPT,'post_status'=>'publish','numberposts'=>-1,
                'meta_key'=>'cc_trigger','meta_value'=>'login'
            ]);
            foreach($posts as $p){
                $m=get_post_meta($p->ID);
                $out[]=[
                    'name'=>$m['cc_name'][0]??'',
                    'value'=>$m['cc_value'][0]??'',
                    'duration'=>intval($m['cc_duration'][0]??30),
                ];
            }
            return $out;
        }
    }

    Cookie_Creator::instance();
}
