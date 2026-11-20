# assd_tools — Dashboard de specs ASDD

Panel web ligero para visualizar y editar en línea las specs de cualquier
proyecto que siga el flujo
[ASDD](https://github.com/efactory-llc/asdd).

---

## Estructura de archivos

```
assd_tools/
  config.json        ← configuración del proyecto (editar esto)
  index.html         ← dashboard (no tocar)
  specs.json         ← generado automáticamente por gen-specs-json.sh
  running.json       ← estado del agente en curso (gestionado por hooks)
  save-spec.php      ← endpoint de escritura (guarda specs desde el dashboard)
  specs/             ← directorio servido estáticamente con los .md de specs
                        (puede ser un enlace simbólico a ../specs)
```

---

## Configuración (`config.json`)

```json
{
  "project_name": "mi-proyecto",
  "specs_dir":    "../specs",
  "output_json":  "specs.json"
}
```

| Campo | Descripción | Default |
|---|---|---|
| `project_name` | Nombre que muestra el dashboard en la cabecera | — |
| `specs_dir` | Ruta al directorio de specs, **relativa a `assd_tools/`** | `../specs` |
| `output_json` | Nombre del JSON generado, dentro de `assd_tools/` | `specs.json` |

---

## Instalar en un proyecto nuevo

1. **Copia** la carpeta `assd_tools/` a la raíz de tu proyecto.

2. **Copia** `scripts/gen-specs-json.sh` a tu directorio `scripts/`.

3. **Edita** `assd_tools/config.json`:
   - Cambia `project_name`.
   - Ajusta `specs_dir` si tu directorio de specs no es `../specs`.

4. **Enlaza** (o copia) tus specs para que Apache pueda servirlas:
   ```bash
   ln -s ../specs assd_tools/specs
   ```
   Si usas una copia en lugar de un enlace, recuerda mantenerla sincronizada.

5. **Genera el JSON inicial**:
   ```bash
   bash scripts/gen-specs-json.sh
   ```

6. **Configura Apache** para que `assd_tools/` sea accesible y PHP pueda
   escribir (el proceso `www-data` necesita permisos de escritura sobre
   `specs/` y `assd_tools/specs.json`):
   ```apache
   Alias /assd_tools /ruta/a/tu/proyecto/assd_tools
   <Directory /ruta/a/tu/proyecto/assd_tools>
       Options -Indexes
       AllowOverride None
       Require all granted
   </Directory>
   ```

7. Abre `http://tu-proyecto.local/assd_tools/` en el navegador.

---

## Regenerar `specs.json` manualmente

```bash
bash scripts/gen-specs-json.sh
```

El script se puede llamar desde cualquier directorio. Lee la configuración de
`assd_tools/config.json` y vuelca el resultado en `assd_tools/specs.json`.

Las rutas se pueden sobreescribir con variables de entorno si necesitas un
flujo no estándar:

```bash
SPECS_DIR=/ruta/absoluta/specs OUTPUT=/otra/ruta/out.json bash scripts/gen-specs-json.sh
```

---

## Dependencias

| Herramienta | Para qué |
|---|---|
| PHP ≥ 8.0 | Servir `save-spec.php` |
| `jq` | Parsear frontmatter y generar JSON |
| Apache (o nginx) | Servir el dashboard y los `.md` de specs |

---

## Seguridad

- `save-spec.php` valida el nombre del fichero con una expresión regular
  (`/^\d{3}-[a-z0-9_-]+\.md$/i`) para evitar *path traversal*.
- Si el dashboard no necesita edición en producción, retira o protege con
  autenticación el endpoint `save-spec.php`.
