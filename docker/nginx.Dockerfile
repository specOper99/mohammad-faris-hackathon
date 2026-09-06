FROM nginx:1.27-alpine

COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
RUN sed -i 's/\r$//' /etc/nginx/conf.d/default.conf
