Root CA Certificate

openssl genrsa -out root_ca.key 4096
openssl req -x509 -new -nodes -key root_ca.key -sha384 -days 3650 -out root_ca.crt -subj "/C=US/O=Apple Inc./OU=Apple Certification Authority/CN=Apple Root CA - G2"

Intermediate CA Certificate

openssl genrsa -out intermediate_ca.key 4096
openssl req -new -key intermediate_ca.key -out intermediate_ca.csr -subj "/C=US/O=Apple Inc./OU=Apple Certification Authority/CN=Apple Intermediate CA"
openssl x509 -req -in intermediate_ca.csr -CA root_ca.crt -CAkey root_ca.key -CAcreateserial -out intermediate_ca.crt -days 3650 -sha384

Leaf Certificate for Server:

openssl genrsa -out server.key 4096
openssl req -new -key server.key -out server.csr -subj "/C=US/O=Apple Inc./OU=Apple Certification Authority/CN=activation.apple.com"
openssl x509 -req -in server.csr -CA intermediate_ca.crt -CAkey intermediate_ca.key -CAcreateserial -out server.crt -days 3650 -sha384
