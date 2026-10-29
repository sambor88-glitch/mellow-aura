// Stands in for nginx and Cloudflare in front of `php artisan serve`, which sends every file uncompressed.
// Without it Lighthouse would measure the dev server, not what the shop's visitors download.
// Usage: node compress-proxy.mjs <listen port> <upstream port>
import http from 'node:http';
import zlib from 'node:zlib';

const [listenPort, upstreamPort] = process.argv.slice(2).map(Number);
const compressible = /^(text\/|application\/(javascript|json|xml|ld\+json|manifest\+json)|image\/svg\+xml)/;

http.createServer((request, response) => {
    const upstream = http.request(
        { host: '127.0.0.1', port: upstreamPort, path: request.url, method: request.method, headers: request.headers },
        (reply) => {
            const headers = { ...reply.headers };
            const accepts = request.headers['accept-encoding'] ?? '';
            const encoding = accepts.includes('br') ? 'br' : accepts.includes('gzip') ? 'gzip' : null;

            if (!encoding || headers['content-encoding'] || !compressible.test(headers['content-type'] ?? '')) {
                response.writeHead(reply.statusCode, headers);
                reply.pipe(response);
                return;
            }

            delete headers['content-length'];
            headers['content-encoding'] = encoding;
            headers.vary = headers.vary ? `${headers.vary}, Accept-Encoding` : 'Accept-Encoding';
            response.writeHead(reply.statusCode, headers);
            reply.pipe(encoding === 'br' ? zlib.createBrotliCompress() : zlib.createGzip()).pipe(response);
        },
    );

    upstream.on('error', () => {
        response.writeHead(502);
        response.end();
    });
    request.pipe(upstream);
}).listen(listenPort, '127.0.0.1');
