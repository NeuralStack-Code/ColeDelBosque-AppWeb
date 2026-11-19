-- ============================================================================================
-- Catálogo de permisos del colegio (2026-11-19) · las TABLAS. Base del colegio.
--
-- Igual que en NeuralStack Code: Componentes (qué controller atiende cada URL), Objetos (grupo · pantalla · acción),
-- ObjetoUsos (una pantalla que llama acciones de otro controller), Perfiles, PerfilObjeto (qué concede cada perfil),
-- BitacoraPermisos (cada cambio; su último Id es la "revisión" con la que se refresca el menú en caché) y
-- CatalogoVersion (cada carga de la semilla). Diferencias del colegio:
--   CuentaPerfiles   los perfiles se asignan a la CUENTA (la que entra con su matrícula), N:M
--   Perfiles.Inicio  pantalla de inicio de quien tiene el perfil ('/maestro', '/dashboard/index')
--   Perfiles.TipoCuenta  el perfil que recibe sola una cuenta nueva de ese tipo (cuenta.permiso_id); NULL = ninguno
-- Y Rutas: las páginas sueltas del sitio y las URLs anteriores que redirigen (antes estaban escritas en index.php).
--
-- Las columnas de auditoría se llaman como en el framework (IdUsuarioRegistra / IdUsuarioCambia) pero guardan la
-- CUENTA que actuó (Command::actor = la cuenta en sesión; 0 = el sistema).
-- Solo crea lo que falta (IF NOT EXISTS): se puede correr dos veces. MariaDB 10.6+ / MySQL 8.
-- ============================================================================================
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS TipoObjetos (
    IdTipoObjeto      INT NOT NULL,
    Nombre            VARCHAR(30) NOT NULL,
    FechaRegistro     DATETIME DEFAULT CURRENT_TIMESTAMP,
    IdUsuarioRegistra INT NOT NULL DEFAULT 0,
    NumeroCambios     INT NOT NULL DEFAULT 0,
    FechaUltimoCambio DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    IdUsuarioCambia   INT DEFAULT NULL,
    PRIMARY KEY (IdTipoObjeto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO TipoObjetos (IdTipoObjeto, Nombre) VALUES (1, 'Grupo'), (2, 'Vista'), (3, 'Método');

CREATE TABLE IF NOT EXISTS Componentes (
    IdComponente      INT NOT NULL AUTO_INCREMENT,
    Clave             VARCHAR(50) NOT NULL,                  -- 'ciclo' (la URL /ciclo/...); '' = los grupos
    Clase             VARCHAR(80) NOT NULL DEFAULT '',       -- 'CicloController'
    Archivo           VARCHAR(160) NOT NULL DEFAULT '',      -- 'webService/controllers/CicloController.php'
    Modulo            VARCHAR(40) NOT NULL DEFAULT '',       -- carpeta de business/models que activa el Loader
    Conexion          VARCHAR(20) NOT NULL DEFAULT 'principal',
    Sistema           VARCHAR(10) NOT NULL DEFAULT 'portal',
    FechaRegistro     DATETIME DEFAULT CURRENT_TIMESTAMP,
    IdUsuarioRegistra INT NOT NULL DEFAULT 0,
    NumeroCambios     INT NOT NULL DEFAULT 0,
    FechaUltimoCambio DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    IdUsuarioCambia   INT DEFAULT NULL,
    PRIMARY KEY (IdComponente),
    UNIQUE KEY UQ_Componentes_Clave (Clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Objetos (
    IdObjeto          INT NOT NULL AUTO_INCREMENT,
    IdTipoObjeto      INT NOT NULL,                          -- 1 grupo · 2 vista · 3 método
    IdComponente      INT NOT NULL,
    IdPadre           INT DEFAULT NULL,
    Clave             VARCHAR(80) NOT NULL,                  -- el nombre exacto del método ('vistaPrevia')
    Nombre            VARCHAR(120) NOT NULL DEFAULT '',
    Icono             VARCHAR(60) NOT NULL DEFAULT '',
    Orden             INT NOT NULL DEFAULT 0,
    SoloPost          TINYINT(1) NOT NULL DEFAULT 1,
    Acceso            ENUM('publico','sesion','perfil') NOT NULL DEFAULT 'perfil',
    Sensible          TINYINT(1) NOT NULL DEFAULT 0,         -- solo un perfil EsAdmin lo concede
    PaginaAnterior    VARCHAR(50) DEFAULT NULL,
    Activo            TINYINT(1) NOT NULL DEFAULT 1,
    FechaRegistro     DATETIME DEFAULT CURRENT_TIMESTAMP,
    IdUsuarioRegistra INT NOT NULL DEFAULT 0,
    NumeroCambios     INT NOT NULL DEFAULT 0,
    FechaUltimoCambio DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    IdUsuarioCambia   INT DEFAULT NULL,
    PRIMARY KEY (IdObjeto),
    UNIQUE KEY UQ_Objetos_Componente_Clave (IdComponente, Clave),
    KEY IX_Objetos_Padre (IdPadre),
    KEY FK_Objetos_Tipo (IdTipoObjeto),
    CONSTRAINT FK_Objetos_Componente FOREIGN KEY (IdComponente) REFERENCES Componentes (IdComponente),
    CONSTRAINT FK_Objetos_Padre      FOREIGN KEY (IdPadre)      REFERENCES Objetos (IdObjeto) ON DELETE SET NULL,
    CONSTRAINT FK_Objetos_Tipo       FOREIGN KEY (IdTipoObjeto) REFERENCES TipoObjetos (IdTipoObjeto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ObjetoUsos (
    IdObjeto          INT NOT NULL,                          -- la pantalla
    IdObjetoMetodo    INT NOT NULL,                          -- lo que llama (de otro controller)
    FechaRegistro     DATETIME DEFAULT CURRENT_TIMESTAMP,
    IdUsuarioRegistra INT NOT NULL DEFAULT 0,
    NumeroCambios     INT NOT NULL DEFAULT 0,
    FechaUltimoCambio DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    IdUsuarioCambia   INT DEFAULT NULL,
    PRIMARY KEY (IdObjeto, IdObjetoMetodo),
    KEY FK_ObjetoUsos_Metodo (IdObjetoMetodo),
    CONSTRAINT FK_ObjetoUsos_Metodo FOREIGN KEY (IdObjetoMetodo) REFERENCES Objetos (IdObjeto) ON DELETE CASCADE,
    CONSTRAINT FK_ObjetoUsos_Vista  FOREIGN KEY (IdObjeto)       REFERENCES Objetos (IdObjeto) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Perfiles (
    IdPerfil          INT NOT NULL AUTO_INCREMENT,
    Nombre            VARCHAR(80) NOT NULL,
    Descripcion       VARCHAR(255) NOT NULL DEFAULT '',
    EsAdmin           TINYINT(1) NOT NULL DEFAULT 0,         -- concede todo sin filas en PerfilObjeto
    Activo            TINYINT(1) NOT NULL DEFAULT 1,
    Inicio            VARCHAR(80) NOT NULL DEFAULT '',       -- pantalla de inicio ('/maestro'); '' = la portada
    TipoCuenta        INT DEFAULT NULL,                      -- cuenta.permiso_id que lo recibe sola al darse de alta
    FechaRegistro     DATETIME DEFAULT CURRENT_TIMESTAMP,
    IdUsuarioRegistra INT NOT NULL DEFAULT 0,
    NumeroCambios     INT NOT NULL DEFAULT 0,
    FechaUltimoCambio DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    IdUsuarioCambia   INT DEFAULT NULL,
    PRIMARY KEY (IdPerfil),
    UNIQUE KEY UQ_Perfiles_Nombre (Nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS PerfilObjeto (
    IdPerfil          INT NOT NULL,
    IdObjeto          INT NOT NULL,
    FechaRegistro     DATETIME DEFAULT CURRENT_TIMESTAMP,
    IdUsuarioRegistra INT NOT NULL DEFAULT 0,
    NumeroCambios     INT NOT NULL DEFAULT 0,
    FechaUltimoCambio DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    IdUsuarioCambia   INT DEFAULT NULL,
    PRIMARY KEY (IdPerfil, IdObjeto),
    KEY IX_PerfilObjeto_Objeto (IdObjeto),
    CONSTRAINT FK_PerfilObjeto_Objeto FOREIGN KEY (IdObjeto) REFERENCES Objetos (IdObjeto)   ON DELETE CASCADE,
    CONSTRAINT FK_PerfilObjeto_Perfil FOREIGN KEY (IdPerfil) REFERENCES Perfiles (IdPerfil) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Al borrar una cuenta se van sus perfiles (igual que sus reportes semanales).
CREATE TABLE IF NOT EXISTS CuentaPerfiles (
    IdCuenta          INT NOT NULL,
    IdPerfil          INT NOT NULL,
    FechaRegistro     DATETIME DEFAULT CURRENT_TIMESTAMP,
    IdUsuarioRegistra INT NOT NULL DEFAULT 0,
    NumeroCambios     INT NOT NULL DEFAULT 0,
    FechaUltimoCambio DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    IdUsuarioCambia   INT DEFAULT NULL,
    PRIMARY KEY (IdCuenta, IdPerfil),
    KEY IX_CuentaPerfiles_Perfil (IdPerfil),
    CONSTRAINT FK_CuentaPerfiles_Perfil FOREIGN KEY (IdPerfil) REFERENCES Perfiles (IdPerfil) ON DELETE CASCADE,
    CONSTRAINT FK_CuentaPerfiles_Cuenta FOREIGN KEY (IdCuenta) REFERENCES cuenta (id_cuenta) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS BitacoraPermisos (
    IdBitacora        INT NOT NULL AUTO_INCREMENT,
    Accion            VARCHAR(40) NOT NULL,
    Detalle           JSON DEFAULT NULL,
    FechaRegistro     DATETIME DEFAULT CURRENT_TIMESTAMP,
    IdUsuarioRegistra INT NOT NULL DEFAULT 0,
    NumeroCambios     INT NOT NULL DEFAULT 0,
    FechaUltimoCambio DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    IdUsuarioCambia   INT DEFAULT NULL,
    PRIMARY KEY (IdBitacora),
    KEY IX_BitacoraPermisos_Accion (Accion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS CatalogoVersion (
    IdCatalogoVersion INT NOT NULL AUTO_INCREMENT,
    Huella            CHAR(64) NOT NULL,
    Objetos           INT NOT NULL DEFAULT 0,
    Origen            VARCHAR(20) NOT NULL DEFAULT 'semilla',
    FechaRegistro     DATETIME DEFAULT CURRENT_TIMESTAMP,
    IdUsuarioRegistra INT NOT NULL DEFAULT 0,
    NumeroCambios     INT NOT NULL DEFAULT 0,
    FechaUltimoCambio DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    IdUsuarioCambia   INT DEFAULT NULL,
    PRIMARY KEY (IdCatalogoVersion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rutas del sitio (core/Rutas.php, en caché 5 minutos):
--   vista        la URL muestra una página suelta (Destino = el archivo dentro de webService/views/)
--   redireccion  la URL manda a otra (Destino = la ruta interna), con su código (301/302) y, si ConservarQuery, su ?query
-- Todo lo demás es /<componente>/<método> del catálogo.
CREATE TABLE IF NOT EXISTS Rutas (
    IdRuta            INT NOT NULL AUTO_INCREMENT,
    Ruta              VARCHAR(150) NOT NULL,                 -- sin '/' al inicio ni al final: '' = la portada
    Tipo              VARCHAR(12) NOT NULL,                  -- 'vista' | 'redireccion'
    Destino           VARCHAR(255) NOT NULL,
    CodigoHttp        SMALLINT NOT NULL DEFAULT 301,
    ConservarQuery    TINYINT(1) NOT NULL DEFAULT 1,
    Descripcion       VARCHAR(150) NOT NULL DEFAULT '',
    Activo            TINYINT(1) NOT NULL DEFAULT 1,
    FechaRegistro     DATETIME DEFAULT CURRENT_TIMESTAMP,
    IdUsuarioRegistra INT NOT NULL DEFAULT 0,
    NumeroCambios     INT NOT NULL DEFAULT 0,
    FechaUltimoCambio DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    IdUsuarioCambia   INT DEFAULT NULL,
    PRIMARY KEY (IdRuta),
    UNIQUE KEY UX_Rutas_Ruta (Ruta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
