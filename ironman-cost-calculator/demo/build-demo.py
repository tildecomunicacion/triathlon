#!/usr/bin/env python3
"""Genera la versión autónoma (un solo HTML) de la calculadora.

Inlinea el CSS y los tres scripts del plugin en demo/plantilla.html, de modo
que la demo y el plugin salen siempre de los mismos ficheros: no hay dos
versiones del modelo que se puedan desincronizar.

    python3 demo/build-demo.py            # -> demo/calculadora-ironman.html
    python3 demo/build-demo.py salida.html
"""
import pathlib
import sys

RAIZ = pathlib.Path(__file__).resolve().parent.parent

PIEZAS = {
    '/* __CSS_CALCULADORA__ */': 'assets/css/icc-frontend.css',
    '/* __JS_DATOS__ */': 'assets/js/icc-data.js',
    '/* __JS_MOTOR__ */': 'assets/js/icc-engine.js',
    '/* __JS_UI__ */': 'assets/js/icc-ui.js',
}


def main():
    html = (RAIZ / 'demo' / 'plantilla.html').read_text(encoding='utf-8')

    for marca, ruta in PIEZAS.items():
        if marca not in html:
            sys.exit('Falta la marca %s en la plantilla' % marca)
        contenido = (RAIZ / ruta).read_text(encoding='utf-8')
        if '</script' in contenido.lower():
            sys.exit('%s contiene un cierre de script y rompería el HTML' % ruta)
        html = html.replace(marca, contenido)

    destino = pathlib.Path(sys.argv[1]) if len(sys.argv) > 1 else RAIZ / 'demo' / 'calculadora-ironman.html'
    destino.write_text(html, encoding='utf-8')
    print('%s (%.0f KB)' % (destino, destino.stat().st_size / 1024))


if __name__ == '__main__':
    main()
