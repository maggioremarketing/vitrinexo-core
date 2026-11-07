/**
 * Que no quede un color escrito a mano donde se pueda nombrar un papel, y que
 * donde no se pueda, el valor exista en la paleta.
 *
 * El plugin tiene una excepción de verdad: un cliente de correo no entiende
 * variables CSS, el panel de WordPress no carga la hoja del tema, una página
 * de wp_die() no carga ninguna, y un SVG que se arma en el servidor tampoco.
 * Ahí el valor va literal y lo único que se comprueba es que exista en la
 * paleta.
 *
 * Lo que esta prueba agrega es el límite de esa excepción. Antes valía para
 * todo el repositorio, así que un hexadecimal en un shortcode —que sí carga
 * el sistema de diseño— pasaba igual que uno en una plantilla de correo. Hoy
 * no hay ninguno, pero eso era suerte, no una regla: nada lo impedía.
 *
 *   node pruebas-colores.mjs
 */

import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join, relative } from 'node:path';

const RAIZ = fileURLToPath( new URL( '.', import.meta.url ) );

/**
 * Dónde el literal está permitido, y por qué. La lista es de rutas porque es
 * lo único que se puede medir, pero el motivo es siempre el mismo: ahí la hoja
 * del tema no está cargada, así que var(--token) no resolvería a nada.
 */
const SIN_HOJA_DE_ESTILO = [
  [ 'modules/email/',      'plantillas de correo: un cliente de correo no entiende variables CSS' ],
  [ 'modules/admin/',      'pantallas de wp-admin: el panel no carga la hoja del tema' ],
  [ 'vitrinexo-core.php',  'páginas de opciones de wp-admin y el documento imprimible, que es un HTML suelto' ],
  [ 'rest/',               'páginas de wp_die(), que no cargan ninguna hoja' ],
  [ 'helpers/avatar.php',  'un SVG que se arma en el servidor, sin CSS alrededor' ],
];
const literalPermitido = ( ruta ) => SIN_HOJA_DE_ESTILO.some( ( [ p ] ) => ruta.startsWith( p ) );

/**
 * La paleta sale del tema cuando el tema está al lado, y de la copia de abajo
 * cuando no. Si los dos están y no coinciden, la prueba falla: una copia que
 * se desincroniza en silencio es peor que no tener copia, porque deja pasar
 * colores que ya no existen.
 */
// Una fila por familia, en el orden del tema: base, hover, presión, suave y
// tinta. Las seis tintas de familia (lavanda para abajo) faltaban acá: el tema
// las agregó para tener un color de texto que llegue al contraste dentro de
// cada familia, y esta copia se quedó con la paleta de antes.
const COPIA = [
  '#FFFFFF', '#F4F1EC', '#EFE8DE', '#E6E2DB', '#14161F', '#5C5F6B',
  '#FF7A4D', '#F26A3B', '#E55F31', '#FFE3D8', '#AD4319',
  '#CBC3FF', '#B9B2E9', '#A6A0D2', '#EAE7FF', '#6A6686',
  '#F4C6DC', '#DEB4C9', '#C7A3B6', '#FBE8F1', '#7D6571',
  '#A9C6E8', '#9AB4D4', '#8BA3C0', '#DDE8F6', '#5A697B',
  '#7DBF9E', '#72AE91', '#689D85', '#CBE5D8', '#456A58',
  '#F2EC79', '#DCD770', '#C6C167', '#FAF7C9', '#73703A',
  '#C58D78', '#B3816F', '#A27566', '#E8D1C9', '#775548',
];

const fallos = [];
const TEMA = join( RAIZ, '../vitrinexo-theme/assets/css/style.css' );
let PALETA = new Set( COPIA ), origen = 'la copia de este archivo (el tema no está al lado)';

if ( existsSync( TEMA ) ) {
  const css = readFileSync( TEMA, 'utf8' );
  // Sin los comentarios: el :root cita #B4471F para contar por qué ese coral no
  // llegaba al contraste mínimo, y un color citado no es un token.
  const raiz = css.slice( css.indexOf( ':root' ), css.indexOf( '\n}', css.indexOf( ':root' ) ) )
    .replace( /\/\*[\s\S]*?\*\//g, '' );
  const delTema = [ ...raiz.matchAll( /#[0-9a-fA-F]{6}\b/g ) ].map( ( m ) => m[ 0 ].toUpperCase() );
  const enElTema = new Set( delTema );
  origen = 'el :root del tema';
  PALETA = enElTema;

  const sobran = COPIA.filter( ( c ) => ! enElTema.has( c ) );
  const faltan = [ ...enElTema ].filter( ( c ) => ! COPIA.includes( c ) );
  if ( sobran.length || faltan.length ) {
    fallos.push( `la copia de la paleta de este archivo ya no es la del tema: sobran ${ sobran.join( ' ' ) || '—' }, faltan ${ faltan.join( ' ' ) || '—' }` );
  }
}

function archivos( carpeta = '' ) {
  const salida = [];
  for ( const nombre of readdirSync( join( RAIZ, carpeta ) ) ) {
    if ( [ '.git', 'node_modules' ].includes( nombre ) ) continue;
    const ruta = carpeta ? `${ carpeta }/${ nombre }` : nombre;
    if ( statSync( join( RAIZ, ruta ) ).isDirectory() ) salida.push( ...archivos( ruta ) );
    else if ( [ '.php', '.js', '.mjs', '.css' ].some( ( e ) => nombre.endsWith( e ) ) ) salida.push( ruta );
  }
  return salida;
}

for ( const archivo of archivos() ) {
  if ( archivo.startsWith( 'pruebas-' ) ) continue; // la prueba cita colores a propósito
  const permitido = literalPermitido( archivo );

  readFileSync( join( RAIZ, archivo ), 'utf8' ).split( '\n' ).forEach( ( linea, i ) => {
    if ( linea.includes( 'color ajeno' ) ) return;
    const donde = `${ relative( '.', archivo ) }:${ i + 1 }`;

    // Un color translúcido escrito a mano es el mismo problema con otra forma,
    // y por acá sobrevivió el turquesa viejo en el foco de las preguntas
    // frecuentes, en una página publicada, mucho después del repintado.
    const fn = linea.match( /\b(?:rgba?|hsla?)\(\s*[0-9.]/ );
    if ( fn ) {
      const nombre = fn[ 0 ].match( /[a-z]+/ )[ 0 ];
      fallos.push( `${ donde } escribe un color en ${ nombre }(): lo translúcido se escribe con color-mix() sobre un token` );
      return;
    }

    for ( const c of linea.match( /#[0-9a-fA-F]{3,8}\b/g ) ?? [] ) {
      if ( c.length === 4 ) continue;
      if ( ! permitido ) {
        fallos.push( `${ donde } escribe ${ c } a mano: acá la hoja del tema está cargada, así que va var(--token)` );
      } else if ( ! PALETA.has( c.toUpperCase() ) ) {
        fallos.push( `${ donde } escribe ${ c }, que no está en la paleta` );
      }
    }
  } );
}

if ( fallos.length ) {
  console.log( '  ✕ colores' );
  for ( const f of fallos.slice( 0, 20 ) ) console.log( `      ${ f }` );
  if ( fallos.length > 20 ) console.log( `      … y ${ fallos.length - 20 } más` );
  process.exit( 1 );
}
console.log( `  ✓ colores  (paleta leída de ${ origen })` );
