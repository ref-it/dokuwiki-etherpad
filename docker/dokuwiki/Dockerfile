FROM php:8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      curl \
      ca-certificates \
 && a2enmod rewrite \
 && rm -rf /var/lib/apt/lists/*

# Both containers run with network_mode: host (see docker-compose.yml) so
# that "localhost:<etherpad-port>" resolves identically for the browser and
# for PHP's server-side curl calls to the Etherpad API. That means this
# container shares the host's port space, so move Apache off port 80.
RUN sed -i 's/Listen 80/Listen 8089/' /etc/apache2/ports.conf \
 && sed -i 's/:80>/:8089>/' /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

RUN curl -fsSL https://download.dokuwiki.org/src/dokuwiki/dokuwiki-stable.tgz -o /tmp/dokuwiki.tgz \
 && tar xzf /tmp/dokuwiki.tgz --strip-components=1 -C /var/www/html \
 && rm /tmp/dokuwiki.tgz \
 && chown -R www-data:www-data /var/www/html \
 # data/ and conf/ get shadowed by empty named volumes at runtime - keep a
 # pristine copy so the entrypoint can seed them on first start.
 && mkdir -p /var/www/html-defaults \
 && cp -r /var/www/html/data /var/www/html-defaults/data

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
