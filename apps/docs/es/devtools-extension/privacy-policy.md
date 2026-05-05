# Política de Privacidad — Extensión Arqel DevTools

> **Estado:** vigente • **Versión:** 1.0 • **Fecha de entrada en vigor:** 2026-05-01 • **Aplica a:** `@arqel-dev/devtools-extension` (Chrome, Firefox, Edge)

La extensión **Arqel DevTools** fue diseñada con privacy-first como principio central. Esta política describe, de forma transparente, exactamente qué hace la extensión con los datos del usuario — la respuesta corta es: **nada sale de la máquina del usuario, bajo ninguna circunstancia, sin una acción explícita.**

---

## 1. Resumen ejecutivo

| Pregunta | Respuesta |
|---|---|
| ¿La extensión recopila datos personales? | **No.** |
| ¿La extensión envía datos a servidores externos? | **No.** |
| ¿La extensión usa analytics, telemetría o crash reporting? | **No.** |
| ¿La extensión lee cookies de otros sitios? | **No.** |
| ¿La extensión lee historial de navegación? | **No.** |
| ¿La extensión funciona offline? | **Sí.** Totalmente local. |
| ¿Dónde se almacenan los datos? | Solo en el propio `chrome.storage.local` del browser, en el dispositivo del usuario. |

---

## 2. Lo que la extensión hace

`@arqel-dev/devtools-extension` es una herramienta de debugging para desarrolladores Arqel. Añade un panel a las DevTools del browser que muestra:

- **Resources detectados** en la página actual (lista de clases Resource registradas en el panel Arqel activo).
- **Props Inertia actuales** e historial de navegación Inertia (solo pestaña activa, solo dev mode).
- **Eventos del bridge React DevTools** para componentes Arqel (Field, Form, Table).
- **Markers de performance** desde hooks Arqel (`useArqelForm`, `useArqelTable`).

Todo esto viene **de la propia página inspeccionada**, vía un hook inyectado **solo cuando** `window.__ARQEL_DEV__ === true` — un flag que solo se activa cuando la aplicación está en modo `development` y el desarrollador importa explícitamente `@arqel-dev/react/dev`.

---

## 3. Lo que la extensión NO hace

- **No recopila** ningún dato personal: nombre, email, IP, user-agent, geolocalización, cookies, tokens, contraseñas — nada.
- **No envía** requests HTTP a servidores Arqel ni a terceros. **Cero llamadas de red** por defecto.
- **No usa** Google Analytics, Mixpanel, Sentry, PostHog ni ninguna herramienta de telemetría.
- **No accede** a pestañas que no tengan el flag `__ARQEL_DEV__` activado.
- **No persiste** datos entre sesiones más allá de lo que el usuario explícitamente "marcó como favorito" para inspección (y aun eso vive en `chrome.storage.local`, nunca remoto).
- **No lee** el contenido de otras extensiones instaladas.
- **No modifica** el DOM de la página inspeccionada (read-only).

---

## 4. Permisos solicitados en el manifest

| Permiso | Justificación |
|---|---|
| `devtools` | Requerido para registrar un panel personalizado en DevTools. |
| `storage` | Guarda preferencias locales (tema, panel activo) vía `chrome.storage.local`. |
| `scripting` (opcional) | Inyecta el bridge de debugging **solo** en páginas con el flag `__ARQEL_DEV__`. |

La extensión **no solicita**: `tabs`, `history`, `cookies`, `webRequest`, host permissions `<all_urls>`, `nativeMessaging`, `identity` ni ningún permiso que conceda acceso cross-origin a contenido.

---

## 5. Modo DEV-only — protección por capas

La inyección del bridge solo ocurre cuando **todo** lo siguiente es verdadero:

1. La aplicación importó `@arqel-dev/react/dev` (subpath separado, eliminado del bundle de producción vía tree-shaking).
2. `process.env.NODE_ENV === 'development'`.
3. `window.__ARQEL_DEV__ === true` (activado explícitamente por el desarrollador).

En **producción**, incluso con la extensión instalada, queda inerte — el panel DevTools muestra el mensaje "No Arqel application detected on this tab".

---

## 6. Compartición de datos

**La extensión no comparte datos.** No hay terceros, no hay SDK de analytics embebido, no hay "estadísticas anonimizadas opt-in". El modelo es binario: **cero recolección**.

Si una versión futura llega a considerar añadir telemetría opcional (ej., para entender las features más usadas), vamos a:

- Anunciarlo en el changelog **antes** del release.
- Hacerlo explícitamente opt-in (default: off).
- Actualizar esta política con una nueva versión y fecha de entrada en vigor.
- Documentar claramente los campos recolectados.

Hoy esto **no existe y no está planeado para v0.8.x**.

---

## 7. Cookies, local storage y tracking

La extensión **no usa cookies**. El único storage es `chrome.storage.local`, conteniendo solo:

- `arqel_devtools_theme` — `"light" | "dark"` (preferencia visual).
- `arqel_devtools_active_panel` — string identificando la última pestaña abierta.

Estos datos **nunca salen del dispositivo**.

---

## 8. Seguridad

- La extensión es open-source (MIT) — código auditable en `https://github.com/arqel-dev/arqel/tree/main/packages-js/devtools-extension`.
- Los builds son reproducibles vía `pnpm build`.
- Los source maps se shippean con el paquete de la Web Store, satisfaciendo el requisito de código revisable de Mozilla AMO cuando hay minificación involucrada.
- Sin dependencias de runtime — solo React (ya presente en el host de DevTools).

---

## 9. Cómo eliminar la extensión

En cualquier momento, sin pérdida de datos (ya que no hay datos):

- **Chrome/Edge**: `chrome://extensions` → buscar "Arqel DevTools" → "Remove".
- **Firefox**: `about:addons` → "Extensions" → engranaje al lado de "Arqel DevTools" → "Remove".

El `chrome.storage.local` de la extensión es borrado automáticamente por el browser al desinstalar.

---

## 10. Niños

La extensión es una herramienta profesional de desarrollo. No está dirigida a menores de 13 años y no recopila datos que puedan identificar la edad.

---

## 11. Cambios a esta política

Cualquier cambio material será publicado en:

- Este archivo (con bump de versión y nueva fecha de entrada en vigor).
- `CHANGELOG.md` en la raíz del monorepo.
- Notas de release de la Web Store.

La versión anterior se preserva a través del historial de git.

---

## 12. Contacto

- **Issues**: `https://github.com/arqel-dev/arqel/issues`
- **Email**: `security@arqel.dev` (preguntas de privacidad/seguridad únicamente).
- **Disclosure responsable**: ver `SECURITY.md` en la raíz del monorepo.

---

**Última revisión:** 2026-05-01 • **Próxima revisión planeada:** con el release v0.9.0 o ante un cambio material.
