# docker/subdomain-scanner/Dockerfile

FROM alpine:3.19

# Install Go tools
RUN apk add --no-cache \
    curl \
    bind-tools \
    python3 \
    py3-pip \
    ca-certificates

# Install subfinder (الأقوى في subdomain discovery)
RUN curl -sL https://github.com/projectdiscovery/subfinder/releases/download/v2.6.6/subfinder_2.6.6_linux_amd64.zip \
    -o subfinder.zip && \
    unzip subfinder.zip && \
    mv subfinder /usr/local/bin/ && \
    rm subfinder.zip

# Install dnsx (DNS resolver + validation)
RUN curl -sL https://github.com/projectdiscovery/dnsx/releases/download/v1.2.1/dnsx_1.2.1_linux_amd64.zip \
    -o dnsx.zip && \
    unzip dnsx.zip && \
    mv dnsx /usr/local/bin/ && \
    rm dnsx.zip

# Copy the scanner script
COPY scan.py /scanner/web/sub-domain/scan.py

WORKDIR /scanner/web/sub-domain
ENTRYPOINT ["python3", "scan.py"]