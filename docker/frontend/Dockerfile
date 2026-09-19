# Outdoor Commerce — local development image for Next.js
FROM node:22-bookworm-slim

ARG UID=1000
ARG GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl ca-certificates \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /app \
    && if getent group "${GID}" >/dev/null; then \
         GROUP_NAME="$(getent group "${GID}" | cut -d: -f1)"; \
       else \
         groupadd --gid "${GID}" app; \
         GROUP_NAME=app; \
       fi \
    && if getent passwd "${UID}" >/dev/null; then \
         USER_NAME="$(getent passwd "${UID}" | cut -d: -f1)"; \
       else \
         useradd --uid "${UID}" --gid "${GID}" --shell /bin/bash --create-home app; \
         USER_NAME=app; \
       fi \
    && mkdir -p "/home/${USER_NAME}" \
    && chown -R "${UID}:${GID}" /app "/home/${USER_NAME}" \
    && echo "${USER_NAME}" > /tmp/app-user-name

WORKDIR /app

USER ${UID}:${GID}

ENV NEXT_TELEMETRY_DISABLED=1 \
    npm_config_cache=/tmp/.npm \
    HOME=/tmp

EXPOSE 3000

CMD ["npm", "run", "dev"]
