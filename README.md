# Cookie Creator

Versión: 1.2.3  
Autor: Konstantin WDk  
License: GPL v2 or later  
Text Domain: cookie-creator

---

Descripción

Cookie Creator es un plugin para WordPress que permite crear cookies personalizadas basadas en diferentes eventos o parámetros UTM. Facilita:

- Lectura y normalización de parámetros UTM (ñ→n, espacios o + → _)
- Envoltura opcional de valores en SHA-256
- Creación de cookies al:
  - Capturar UTMs de URL
  - Visitar una página específica o una URL personalizada
  - Hacer clic en un selector CSS (ID o clase)
  - Iniciar sesión como usuario WP
  - Envío exitoso de Contact Form 7
  - Tras cierto tiempo en página (segundos o minutos)
  - Generar un Session ID aleatorio

---

Requisitos

- WordPress 5.0 o superior  
- PHP 7.0 o superior  

---

Instalación

1. Copia la carpeta cookie-creator en tu directorio wp-content/plugins/.  
2. En el panel de administración de WordPress, ve a Plugins → Añadir nuevo y activa Cookie Creator.  
3. Aparecerá un nuevo menú lateral llamado Cookie Creator.

---

Registro de Custom Post Type

El plugin crea un Custom Post Type (cookie_creator) oculto al público, pero accesible desde el back-end para:

- Añadir nuevas “cookies” como ítems configurables.  
- Cada ítem define un trigger, nombre, valor, duración y ajustes específicos.

---

Configuración de la Cookie

Al crear o editar un ítem de Cookie Creator encontrarás dos metaboxes:

1. Configuración de la cookie

Campo                     | Descripción
------------------------- | ---------------------------------------------------------------------------------
Tipo de trigger           | Selecciona entre utm, page_visit, click, login, cf7, time, session.
Nombre de la cookie       | Nombre que tendrá la cookie en el navegador (obligatorio).
Valor por defecto         | Valor a almacenar (salvo en session, donde se genera uno aleatorio).
Duración (días)           | Tiempo de vida de la cookie antes de expirar.
Envolver en SHA256        | Opcional: si está marcado, el valor se guardará como hash SHA-256.
Página a vigilar          | (Trigger page_visit) Elige página o introduce URL personalizada.
ID del elemento           | (Trigger click) Selector CSS (#ID o .clase) que al hacer clic dispara la cookie.
Tiempo en página          | (Trigger time) Especifica segundos y/o minutos tras los cuales se crea la cookie.

La interfaz muestra u oculta solo los campos relevantes según el trigger elegido.

2. Ayuda & ejemplos

Uso en PHP:  
$valor = $_COOKIE['nombre_de_la_cookie'] ?? '';

Uso en JavaScript:  
document.cookie; // cadena con todas las cookies

---

Triggers soportados

Trigger      | Descripción
------------ | ----------------------------------------------------------------------------------------------------------------
UTM          | Por cada parámetro UTM detectado en la URL (utm_source, utm_medium, etc.) se crea cookie individual.
Page Visit   | Al visitar la página o URL configurada.
Click        | Al hacer clic en el elemento con el selector CSS especificado.
Login        | Al iniciar sesión en WordPress.
CF7          | Al enviar exitosamente un formulario de Contact Form 7 (wpcf7mailsent).
Time         | Tras el tiempo especificado (en segundos o minutos) desde que carga la página.
Session      | Genera un Session ID aleatorio de 12 caracteres al cargar la página.

---

¿Cómo funciona por dentro?

1. Backend  
   - Registra el CPT y los metaboxes.  
   - Guarda la configuración al editar/guardar el post.

2. Frontend  
   - Encola un script inline que:
     - Lee todas las configuraciones publicadas.
     - Normaliza y/o hashea valores.
     - Establece cookies usando JavaScript (document.cookie) o crypto.subtle.digest para SHA-256.
   - Para login, atrapa el hook wp_login y usa setcookie() en PHP.

---

Ejemplo de configuración

1. Crear un ítem de Cookie Creator:
   - Tipo: UTM  
   - Nombre: mi_cookie  
   - Duración: 7 días  
   - SHA-256: marcado  
2. Accede a tu sitio con URL:  
   https://tusitio.com/?utm_source=Newsletter&utm_campaign=Lanzamiento  
3. Se crearán dos cookies:
   - mi_cookie_utm_source con valor SHA-256 de newsletter
   - mi_cookie_utm_campaign con valor SHA-256 de lanzamiento

---

Changelog

- 1.2.3
  - Añadido trigger Session ID
  - Mejoras en UI/UX del metabox
- 1.2.2
  - Soporte para envíos de CF7
- 1.2.1
  - Trigger Time on Page con segundos y minutos
- 1.2.0
  - Normalización de UTMs (ñ→n, espacios/+→_)
- 1.1.0
  - Hashing SHA-256 opcional
- 1.0.0
  - Versión inicial con triggers UTM, Page Visit, Click, Login

---

Licencia

Este plugin se distribuye bajo la GPL v2 o posteriores.  
Consulta https://www.gnu.org/licenses/gpl-2.0.html para más detalles.

---

Enlaces de interés

- Página del autor: https://webdesignerk.com  
- Contacto / Soporte: foro de soporte de WordPress.org o repositorio GitHub.
