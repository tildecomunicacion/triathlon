#!/usr/bin/env python3
"""Genera la calculadora en los dos formatos de la casa.

Mismo patrón que calculadora-sudoracion: un index.html autónomo para probar y
una plantilla de página de WordPress con wp_head() y wp_footer(). Las dos
salen de los ficheros de src/, así que no hay dos versiones del modelo.

    python3 build.py [--whatsapp 34600000000]
"""
import argparse
import pathlib

RAIZ = pathlib.Path(__file__).resolve().parent
SRC = RAIZ / 'src'

TITULO = 'Calculadora de Coste Real de un IRONMAN · Planeta Triatlón'
FUENTES = ("@import url('https://fonts.googleapis.com/css2?"
           "family=Barlow+Condensed:ital,wght@0,400;0,600;0,700;1,700;1,800;1,900"
           "&family=Barlow:wght@400;500;600&display=swap');")


def documento(cabeza_extra, pie_extra, whatsapp):
    css = (SRC / 'icc-styles.css').read_text(encoding='utf-8')
    shell = (SRC / 'shell.html').read_text(encoding='utf-8')
    js = '\n'.join((SRC / n).read_text(encoding='utf-8')
                   for n in ('icc-data.js', 'icc-engine.js', 'icc-ui.js'))

    if whatsapp:
        shell = shell.replace('<div class="icc"></div>',
                              '<div class="icc" data-whatsapp="%s"></div>' % whatsapp)

    return '\n'.join([
        '<!DOCTYPE html>',
        '<html lang="es">',
        '<head>',
        '  <meta charset="UTF-8">',
        '  <meta name="viewport" content="width=device-width, initial-scale=1.0">',
        '  <title>%s</title>' % TITULO,
        cabeza_extra,
        '  <style>',
        '    ' + FUENTES,
        '',
        css,
        '  </style>',
        '</head>',
        '<body>',
        '',
        shell,
        '',
        '  <script>',
        js,
        '  </script>',
        pie_extra,
        '</body>',
        '</html>',
        '',
    ])


def fragmento(whatsapp):
    """Versión sin <html>/<head>/<body>, para previsualizar la página embebida."""
    css = (SRC / 'icc-styles.css').read_text(encoding='utf-8')
    shell = (SRC / 'shell.html').read_text(encoding='utf-8')
    js = '\n'.join((SRC / n).read_text(encoding='utf-8')
                    for n in ('icc-data.js', 'icc-engine.js', 'icc-ui.js'))
    if whatsapp:
        shell = shell.replace('<div class="icc"></div>',
                              '<div class="icc" data-whatsapp="%s"></div>' % whatsapp)
    return '\n'.join([
        '<title>Coste real de un IRONMAN</title>',
        '<style>',
        '    ' + FUENTES,
        '',
        css,
        '</style>',
        '',
        shell,
        '',
        '<script>',
        js,
        '</script>',
        '',
    ])


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--whatsapp', default='', help='Número con prefijo, sin signos')
    ap.add_argument('--fragmento', default='', help='Ruta de salida de la versión sin <html>, para previsualizar')
    args = ap.parse_args()

    if args.fragmento:
        pathlib.Path(args.fragmento).write_text(fragmento(args.whatsapp), encoding='utf-8')
        print(args.fragmento)
        return

    html = documento('', '', args.whatsapp)
    (RAIZ / 'index.html').write_text(html, encoding='utf-8')

    php = documento('  <?php wp_head(); ?>', '  <?php wp_footer(); ?>', args.whatsapp)
    php = ('<?php\n'
           '/**\n'
           ' * Template Name: Calculadora Coste IRONMAN\n'
           ' * Template Post Type: page\n'
           ' */\n'
           '// Evitar acceso directo\n'
           "if ( ! defined( 'ABSPATH' ) ) exit;\n"
           '?>\n') + php
    (RAIZ / 'page-calculadora-ironman.php').write_text(php, encoding='utf-8')

    for f in ('index.html', 'page-calculadora-ironman.php'):
        print('%s (%.0f KB)' % (f, (RAIZ / f).stat().st_size / 1024))


if __name__ == '__main__':
    main()
