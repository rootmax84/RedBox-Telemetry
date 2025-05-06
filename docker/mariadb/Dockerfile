FROM mariadb:lts

RUN apt-get update && \
    apt-get -y install mariadb-plugin-rocksdb && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*
