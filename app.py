#!/usr/bin/env python3
import http.server
import os

PORT = 8000
DIR = os.path.dirname(os.path.abspath(__file__))


class Handler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=DIR, **kwargs)

    def end_headers(self):
        self.send_header("Cache-Control", "no-cache, no-store, must-revalidate")
        super().end_headers()


if __name__ == "__main__":
    print(f"Boletín CAMACERO: abre http://localhost:{PORT} en tu navegador.")
    print("Para detener, presiona Ctrl+C.")
    http.server.ThreadingHTTPServer(("127.0.0.1", PORT), Handler).serve_forever()