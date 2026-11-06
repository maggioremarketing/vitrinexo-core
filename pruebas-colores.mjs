/**
 * Que no quede un color escrito a mano fuera de la paleta.
 *
 * Acá el valor va literal y no var(): los correos no entienden variables CSS y
 * el panel de WordPress no carga el sistema de diseño del tema. Lo que se
 * comprueba, entonces, no es que se nombre un papel sino que el valor escrito
 * exista en la paleta.
 *
 *   node pruebas-colores.mjs
 */

import { readdirSync, readFileSync, statSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join, relative } from 'node:path';

const RAIZ = fileURLToPath( new URL( '.', import.meta.url ) );

/** La paleta, tal como la declara el tema. */
const PALETA = new Set( [
  '#FFFFFF', '#F4F1EC', '#EFE8DE', '#E6E2DB', '#14161F', '#5C5F6B',
  '#FF7A4D', '#F26A3B', '#E55F31', '#AD4319', '#FFE3D8',
  '#CBC3FF', '#B9B2E9', '#A6A0D2', '#EAE7FF',
  '#F4C6DC', '#DEB4C9', '#C7A3B6', '#FBE8F1',
  '#A9C6E8', '#9AB4D4', '#8BA3C0', '#DDE8F6',
  '#7DBF9E', '#72AE91', '#689D85', '#CBE5D8',
  '#F2EC79', '#DCD770', '#C6C167', '#FAF7C9',
  '#C58D78', '#B3816F', '#A27566', '#E8D1C9',
] );

const corto = ( c ) => c.length === 4;
const fallos = [];

function archivos( carpeta = '' ) {
  const salida = [];
  for ( const nombre of readdirSync( join( RAIZ, carpeta ) ) ) {
    if ( [ '.git', 'node_modules' ].includes( nombre ) ) continue;
    const ruta = carpeta ? `${ carpeta }/${ nombre }` : nombre;
    if ( statSync( join( RAIZ, ruta ) ).isDirectory() ) salida.push( ...archivos( ruta ) );
    else if ( nombre.endsWith( '.php' ) ) salida.push( ruta );
  }
  return salida;
}

for ( const archivo of archivos() ) {
  readFileSync( join( RAIZ, archivo ), 'utf8' ).split( '\n' ).forEach( ( linea, i ) => {
    if ( linea.includes( 'color ajeno' ) ) return;
    for ( const c of linea.match( /#[0-9a-fA-F]{3,8}\b/g ) ?? [] ) {
      if ( corto( c ) ) continue;
      if ( ! PALETA.has( c.toUpperCase() ) ) {
        fallos.push( `${ relative( '.', archivo ) }:${ i + 1 } escribe ${ c }, que no está en la paleta` );
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
console.log( '  ✓ colores' );
