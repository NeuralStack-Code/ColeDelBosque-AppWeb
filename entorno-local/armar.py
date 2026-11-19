#!/usr/bin/env python3
"""
Arma el SQL del entorno local desde esta misma rama DeployBacklog: la estructura de producción
(esquema/produccion/colegio.sql) y después TODAS las entregas (desplegado/ y pendiente/) en orden.

    python entorno-local/armar.py          (desde la raíz de la rama DeployBacklog)

Genera entorno-local/mysql8/01_colegio.sql, listo para importar en una base VACÍA de MySQL 8 o MariaDB.
Se vuelve a correr cada vez que entra una entrega nueva (y se sube junto con ella).
Si existe esquema/produccion/hasta.txt (la última entrega que ya trae el export), las entregas hasta esa se saltan.
"""
import os, re, glob, datetime

RAIZ = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SALIDA = os.path.join(RAIZ, 'entorno-local', 'mysql8')


def leer(ruta: str) -> str:
    with open(ruta, encoding='utf-8') as f:
        return f.read().replace('\r\n', '\n')


# MariaDB 11 (producción) puede exportar intercalaciones que MySQL 8 no conoce: se cambian por su equivalente.
def a_mysql8(sql: str) -> str:
    sql = sql.replace('utf8mb4_uca1400_ai_ci', 'utf8mb4_0900_ai_ci')
    return re.sub(r'(?<![\w])uca1400_ai_ci', 'utf8mb4_0900_ai_ci', sql)


def entregas():
    """Todas las entregas por nombre (fecha); el mismo día, las desplegadas antes que las pendientes."""
    hasta_txt = os.path.join(RAIZ, 'esquema', 'produccion', 'hasta.txt')
    hasta = leer(hasta_txt).strip() if os.path.isfile(hasta_txt) else ''
    carpetas = []
    for grupo, prioridad in (('desplegado', 0), ('pendiente', 1)):
        for c in glob.glob(os.path.join(RAIZ, grupo, '*')):
            nombre = os.path.basename(c)
            if os.path.isdir(c) and not (hasta and nombre <= hasta):
                carpetas.append((nombre[:10], prioridad, nombre, c))
    return [c for *_, c in sorted(carpetas)]


def main():
    hoy = datetime.date.today().isoformat()
    partes = [f"""-- ============================================================================================
-- Entorno local · base del COLEGIO · generado el {hoy} con entorno-local/armar.py
-- Estructura de producción (esquema/produccion/colegio.sql, sin datos personales) + todas las entregas de
-- DeployBacklog, en orden. NO editar a mano: se regenera. Importar en una base VACÍA (ver entorno-local/README.md).
-- ============================================================================================
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
"""]
    partes.append("\n-- ######## esquema/produccion/colegio.sql ########\n")
    partes.append(a_mysql8(leer(os.path.join(RAIZ, 'esquema', 'produccion', 'colegio.sql'))))
    incluidas = []
    for carpeta in entregas():
        for archivo in sorted(glob.glob(os.path.join(carpeta, '*.sql'))):
            rel = os.path.relpath(archivo, RAIZ).replace(os.sep, '/')
            partes.append(f"\n\nDELIMITER ;\n-- ######## {rel} ########\n")
            partes.append(a_mysql8(leer(archivo)))
        incluidas.append(os.path.basename(carpeta))
    partes.append("\n\nDELIMITER ;\n-- Fin del entorno local.\n")
    os.makedirs(SALIDA, exist_ok=True)
    destino = os.path.join(SALIDA, '01_colegio.sql')
    with open(destino, 'w', encoding='utf-8', newline='\n') as f:
        f.write(''.join(partes))
    print(f'{os.path.relpath(destino, RAIZ)}: estructura de producción + {len(incluidas)} entrega(s)')
    for e in incluidas:
        print('   ', e)


if __name__ == '__main__':
    main()
