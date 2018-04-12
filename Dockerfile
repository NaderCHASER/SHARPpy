FROM python:2.7

RUN apt-get -y update \
    && apt-get install -y libqt4-dev cmake xvfb nginx

RUN pip install numpy==1.13 pyside==1.2.4

RUN echo 'deb http://packages.dotdeb.org jessie all' >> /etc/apt/sources.list
RUN echo 'deb-src http://packages.dotdeb.org jessie all' >> /etc/apt/sources.list
RUN cd /tmp && wget https://www.dotdeb.org/dotdeb.gpg && apt-key add dotdeb.gpg && rm dotdeb.gpg
RUN apt-get update -y

RUN apt-get install -y php7.0 php7.0-fpm

COPY . /sharppy
WORKDIR /sharppy
RUN python setup.py install

RUN ln -sf /data /var/www/data

RUN mv /sharppy/soundings.php /var/www/

RUN rm -rf /var/www/html

RUN mv /sharppy/nginx.vh.default.conf /etc/nginx/conf.d/default.conf

RUN mv /sharppy/nginx.conf /etc/nginx/nginx.conf

WORKDIR /sharppy/runsharp
CMD /sharppy/run.sh
