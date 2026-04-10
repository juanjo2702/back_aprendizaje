# Backend API - Plataforma de Aprendizaje

API RESTful para la plataforma de e-learning con gamificación y certificaciones, construida con Laravel.

## Características

- **Autenticación**: Login/registro tradicional + SSO (Google, GitHub, etc.) con Laravel Sanctum
- **Gestión de cursos**: Catálogo, módulos, lecciones (video, texto, quiz, juegos)
- **Gamificación**: Juegos educativos, puntos, badges, rankings
- **Certificados**: Generación automática de certificados PDF con código QR
- **API RESTful**: Endpoints para frontend y posibles integraciones externas
- **Admin Panel**: Sistema completo de administración con middlewares y políticas

## Tecnologías

- PHP 8.3, Laravel 12
- MySQL 8.0
- Laravel Sanctum (autenticación API)
- Laravel Socialite (SSO)
- Simple QrCode (generación de códigos QR)
- PHPUnit (pruebas)
- Docker + Docker Compose

## Requisitos

- Docker y Docker Compose (recomendado)
- PHP 8.3+ (solo para desarrollo sin Docker)
- Composer (solo para desarrollo sin Docker)

## Instalación con Docker (Recomendado)

1. **Clonar el repositorio**
   ```bash
   git clone https://github.com/juanjo2702/back_aprendizaje.git
   cd back_aprendizaje
   ```

2. **Configurar variables de entorno**
   
   Copiar el archivo de entorno adecuado según el entorno:

   - Desarrollo con Docker: `cp .env.example .env`
   - Producción: `cp .env.production.example .env`

   Luego editar el archivo `.env` para ajustar configuraciones como claves de API, URLs y credenciales de base de datos.

   > **Nota**: Para entornos de producción, generar una nueva APP_KEY con `php artisan key:generate`.

3. **Iniciar servicios**
   ```bash
   docker-compose up -d
   ```

4. **Acceder a la API**
   - Backend API: http://localhost:8000
   - MySQL: localhost:3306 (usuario: root, contraseña: root)

5. **Verificar que los servicios estén funcionando**

   Los contenedores ejecutan automáticamente migraciones y seeders al iniciar. Si necesitas ejecutarlos manualmente:

   ```bash
   docker-compose exec backend php artisan migrate:fresh --seed
   ```

   > **Nota**: Los seeders pueden fallar por límite de memoria; si ocurre, el servidor backend se iniciará igualmente. Para solucionarlo, aumenta la memoria PHP en `docker-compose.yml`.

## Instalación manual (sin Docker)

1. Copiar `.env.example` a `.env` y configurar base de datos
2. Instalar dependencias: `composer install`
3. Generar clave: `php artisan key:generate`
4. Ejecutar migraciones: `php artisan migrate --seed`
5. Iniciar servidor: `php artisan serve`

## Comandos útiles

```bash
composer test          # Ejecutar pruebas PHPUnit
composer lint          # Verificar estilo de código con Laravel Pint
php artisan migrate    # Ejecutar migraciones
php artisan db:seed    # Poblar base de datos con datos demo
```

## Docker

```bash
docker-compose up -d          # Iniciar servicios en segundo plano
docker-compose down           # Detener servicios
docker-compose logs -f        # Ver logs
docker-compose exec backend bash  # Acceder a contenedor backend
```

## Estructura del proyecto

```
back_aprendizaje/
├── app/
│   ├── Http/Controllers/Api/  # Controladores de API
│   ├── Http/Middleware/       # Middlewares (AdminMiddleware, etc.)
│   ├── Models/                # Modelos Eloquent
│   ├── Policies/              # Políticas de autorización
│   └── ...
├── database/migrations/  # Migraciones
├── database/seeders/     # Seeders con datos demo
├── routes/api.php        # Rutas API
├── tests/                # Pruebas PHPUnit
├── .docker/              # Configuración Docker (Nginx, Supervisor)
├── docker/               # Configuraciones adicionales (nginx proxy, mysql)
├── docker-compose.yml    # Orquestación Docker (desarrollo)
├── docker-compose.prod.yml # Orquestación Docker (producción)
└── README.md            # Este archivo
```

## Calidad de código

- **Pruebas**: PHPUnit con cobertura de código (ejecutar `composer test`)
- **Linting**: Laravel Pint (ejecutar `composer lint`)
- **Análisis estático**: PHPStan (ejecutar `composer analyse`)
- **Seguridad**: Validación de entrada, sanitización, protección CSRF, rate limiting

## CI/CD

El proyecto incluye workflows de GitHub Actions:

- **Backend CI**: Ejecuta pruebas PHPUnit, linting y análisis estático en cada push
- **Deploy**: Workflow de ejemplo para despliegue continuo (manual)

Ver `.github/workflows/` para más detalles.

## Despliegue

### Opción 1: Docker Compose (producción)
1. Construir imágenes con `docker-compose -f docker-compose.prod.yml build`
2. Configurar variables de entorno de producción
3. Ejecutar `docker-compose -f docker-compose.prod.yml up -d`

### Opción 2: Servidores tradicionales
- Configurar Nginx + PHP-FPM
- Base de datos: MySQL con replicación recomendada

### Opción 3: Plataformas cloud
- **Laravel Forge / Vapor**
- **AWS ECS / Fargate**
- **Google Cloud Run**
- **Heroku**

## Datos demo

El proyecto incluye seeders con:
- 10 cursos de ejemplo (programación, diseño, marketing)
- 5 usuarios (estudiante, instructor, admin)
- 50+ lecciones con contenido variado
- 20+ juegos y quizzes
- Sistema de badges y certificados

Para cargar datos demo:
```bash
docker-compose exec backend php artisan db:seed
```

## Licencia

Este proyecto está bajo la licencia MIT.

## Contribución

1. Fork el repositorio
2. Crear rama (`git checkout -b feature/mejora`)
3. Commit cambios (`git commit -am 'Agrega nueva funcionalidad'`)
4. Push a la rama (`git push origin feature/mejora`)
5. Crear Pull Request

## Contacto

Para preguntas o soporte, abrir un issue en el repositorio.

---

**Nota**: Este es el backend del proyecto completo. Para el frontend, consulta [front_aprendizaje](https://github.com/juanjo2702/front_aprendizaje).
