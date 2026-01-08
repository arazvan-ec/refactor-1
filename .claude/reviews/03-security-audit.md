# Informe de Auditoría de Seguridad

## Feature: PROD-99999 - Orquestación de Multimedia con Widget-Client

**Fecha:** 2026-01-08

---

## Resumen Ejecutivo

**Hallazgos totales:** 8
- Críticos: 1
- Altos: 2
- Medios: 2
- Bajos: 2
- Informativos: 1

---

## Hallazgos de Seguridad

### [CRÍTICO] SEC-001: Credenciales Hardcodeadas en .env.dist

**Archivo:** `.env.dist`
**Líneas:** 4, 18-19, 24, 66

**Descripción:**
El archivo `.env.dist` contiene credenciales reales que NO deberían estar en control de versiones:

```
APP_SECRET=993149fe6744f42bc90feb14ae82f409
ELASTIC_USER=app_stream
ELASTIC_PASSWORD=afe*43asdv
MQ_CONF_PASSWORD=svgb7643$$1cew
MEMBERSHIP_ACCESS_TOKEN=f47674e84448fcc6752764bd313567cc03b86330
```

**Impacto:**
- Exposición de secretos de aplicación, Elasticsearch, RabbitMQ y tokens de acceso
- Cualquier persona con acceso al repositorio puede comprometer múltiples servicios
- **OWASP A02:2021** - Cryptographic Failures
- **OWASP A07:2021** - Identification and Authentication Failures

**Recomendación:**
1. **ROTAR INMEDIATAMENTE** todas las credenciales expuestas
2. Usar valores placeholder: `APP_SECRET=change_me_in_production`
3. Implementar gestión de secretos (HashiCorp Vault, AWS Secrets Manager)

**CWE:** CWE-798, CWE-312

---

### [ALTO] SEC-002: Verificación SSL Deshabilitada

**Archivo:** `config/packages/httplug.yaml` (línea 43)

```yaml
clients:
    monolog:
        config:
            verify: false  # PELIGROSO
```

**Impacto:**
- Vulnerable a ataques Man-in-the-Middle (MitM)
- Credenciales de Elasticsearch transmitidas sin protección
- **OWASP A02:2021** - Cryptographic Failures

**Recomendación:**
- Establecer `verify: true`
- Si hay problemas de certificados, configurar CA bundle correcto

**CWE:** CWE-295

---

### [ALTO] SEC-003: Potencial SSRF en Widget-Client

**Archivos:**
- `src/Orchestrator/Chain/Multimedia/MultimediaWidgetOrchestrator.php`
- `config/packages/widget/infrastructure.yaml`

**Descripción:**
El `resourceId` se usa directamente sin validación para construir URLs hacia el servicio externo:

```php
$widget = $this->queryWidgetClient->findWidgetById($multimedia->resourceId()->id());
```

**Impacto:**
- Un `resourceId` malicioso con caracteres especiales podría redirigir peticiones
- **OWASP A10:2021** - Server-Side Request Forgery

**Recomendación:**
1. Validar formato del `resourceId` (regex UUID o formato esperado)
2. Implementar whitelist de hosts permitidos
3. URL encoding apropiado del ID

**CWE:** CWE-918

---

### [MEDIO] SEC-004: Falta de Validación en ResourceId

**Archivo:** `vendor/ec/multimedia-domain/src/Domain/Model/Multimedia/ResourceId.php`

```php
public function __construct(string $id = '')
{
    $this->id = $id;  // Sin validación
}
```

**Impacto:**
- IDs maliciosos pueden propagarse por el sistema
- **OWASP A03:2021** - Injection

**CWE:** CWE-20

---

### [MEDIO] SEC-005: Information Disclosure en Excepciones

**Archivo:** `src/Orchestrator/Chain/Multimedia/MultimediaOrchestratorHandler.php`

```php
$message = \sprintf('Orchestrator %s not exist', $multimedia->type());
throw new OrchestratorTypeNotExistException($message);
```

**Impacto:**
- Expone información sobre arquitectura interna
- **OWASP A04:2021** - Insecure Design

**CWE:** CWE-209

---

### [BAJO] SEC-006: Ausencia de Rate Limiting

Clientes HTTP sin rate limiting configurado.

**Impacto:**
- Posible DoS contra servicios backend
- **OWASP A04:2021** - Insecure Design

**CWE:** CWE-770

---

### [BAJO] SEC-007: Falta de Logging de Seguridad

Operaciones con servicios externos sin audit logging.

**Impacto:**
- Dificultad para detectar/responder a incidentes
- **OWASP A09:2021** - Security Logging and Monitoring Failures

**CWE:** CWE-778

---

### [INFORMATIVO] SEC-008: CORS Permisivo en Desarrollo

```bash
CORS_ALLOW_ORIGIN='^https?://(.*\.elconfidencial\.(dev|pre|com)|localhost|127\.0\.0\.1)(:[0-9]+)?$'
```

Aceptable para desarrollo, verificar configuración de producción.

**CWE:** CWE-942

---

## Matriz de Cumplimiento OWASP Top 10 (2021)

| ID | Categoría | Estado |
|----|-----------|--------|
| A01 | Broken Access Control | OK |
| A02 | Cryptographic Failures | **FALLO** |
| A03 | Injection | RIESGO |
| A04 | Insecure Design | RIESGO |
| A05 | Security Misconfiguration | INFO |
| A07 | Auth Failures | **FALLO** |
| A09 | Logging Failures | RIESGO |
| A10 | SSRF | RIESGO |

---

## Recomendaciones Prioritarias

### Antes del Merge (Obligatorio)
1. Remover/rotar credenciales de `.env.dist`
2. Habilitar verificación SSL (`verify: true`)
3. Agregar validación de formato para `resourceId`

### Corto Plazo
4. Implementar whitelist de hosts para widget-client
5. Agregar logging de auditoría
6. Revisar manejo de excepciones

---

## Conclusión

**La implementación presenta riesgos de seguridad significativos.** El hallazgo CRÍTICO (credenciales expuestas) requiere acción inmediata independientemente del estado de la feature.

**Recomendación:** No proceder con el merge hasta resolver hallazgos CRÍTICOS y ALTOS.
