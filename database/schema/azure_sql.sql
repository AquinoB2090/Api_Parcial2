/* =========================================================
   BASE DE DATOS - SUBASTAS DE VEHICULOS
   SQL SERVER / AZURE SQL
   ========================================================= */


/* =========================================================
   1. USUARIOS
   ========================================================= */

CREATE TABLE Usuarios (
    IdUsuario INT IDENTITY(1,1) PRIMARY KEY,

    Nombre VARCHAR(100) NOT NULL,
    Apellido VARCHAR(100) NOT NULL,
    Correo VARCHAR(150) NOT NULL UNIQUE,
    Telefono VARCHAR(20) NOT NULL,

    PasswordHash VARCHAR(255) NOT NULL,

    Rol VARCHAR(20) NOT NULL DEFAULT 'Usuario',

    Activo BIT NOT NULL DEFAULT 1,

    FechaRegistro DATETIME2 NOT NULL DEFAULT GETDATE(),

    CONSTRAINT CK_Usuarios_Rol
        CHECK (Rol IN ('Usuario', 'Admin'))
);


/* =========================================================
   2. VEHICULOS
   ========================================================= */

CREATE TABLE Vehiculos (
    IdVehiculo INT IDENTITY(1,1) PRIMARY KEY,

    IdUsuario INT NOT NULL,

    Anio INT NOT NULL,
    TipoArticulo VARCHAR(50) NOT NULL,
    Marca VARCHAR(100) NOT NULL,
    Modelo VARCHAR(100) NOT NULL,
    Motor VARCHAR(100) NOT NULL,

    Transmision VARCHAR(50) NOT NULL,
    TipoCombustible VARCHAR(50) NOT NULL,

    TrenManejo VARCHAR(10) NOT NULL,

    NumeroCilindros INT NOT NULL,

    EstadoDanio VARCHAR(20) NOT NULL,

    Descripcion VARCHAR(1000),

    FechaPublicacion DATETIME2 NOT NULL DEFAULT GETDATE(),

    Activo BIT NOT NULL DEFAULT 1,

    CONSTRAINT FK_Vehiculos_Usuarios
        FOREIGN KEY (IdUsuario)
        REFERENCES Usuarios(IdUsuario),

    CONSTRAINT CK_Vehiculos_TrenManejo
        CHECK (TrenManejo IN ('AWD', 'FWD', 'RWD', '4WD')),

    CONSTRAINT CK_Vehiculos_EstadoDanio
        CHECK (EstadoDanio IN ('Verde', 'Amarillo', 'Rojo'))
);


/* =========================================================
   3. FOTOS DEL VEHICULO
   ========================================================= */

CREATE TABLE FotosVehiculo (
    IdFoto INT IDENTITY(1,1) PRIMARY KEY,

    IdVehiculo INT NOT NULL,

    UrlFoto VARCHAR(500) NOT NULL,

    EsPrincipal BIT NOT NULL DEFAULT 0,

    OrdenFoto INT NULL,

    CONSTRAINT FK_FotosVehiculo_Vehiculos
        FOREIGN KEY (IdVehiculo)
        REFERENCES Vehiculos(IdVehiculo)
        ON DELETE CASCADE
);


/* =========================================================
   4. SUBASTAS
   ========================================================= */

CREATE TABLE Subastas (
    IdSubasta INT IDENTITY(1,1) PRIMARY KEY,

    IdVehiculo INT NOT NULL,

    MontoBase DECIMAL(12,2) NOT NULL,

    PujaActual DECIMAL(12,2) NULL,

    IdUsuarioPujaActual INT NULL,

    FechaHoraInicio DATETIME2 NOT NULL,
    FechaHoraCierre DATETIME2 NOT NULL,

    Estado VARCHAR(20) NOT NULL DEFAULT 'Pendiente',

    IdGanador INT NULL,

    MontoFinal DECIMAL(12,2) NULL,

    FechaCreacion DATETIME2 NOT NULL DEFAULT GETDATE(),

    CONSTRAINT FK_Subastas_Vehiculos
        FOREIGN KEY (IdVehiculo)
        REFERENCES Vehiculos(IdVehiculo),

    CONSTRAINT FK_Subastas_UsuarioPujaActual
        FOREIGN KEY (IdUsuarioPujaActual)
        REFERENCES Usuarios(IdUsuario),

    CONSTRAINT FK_Subastas_Ganador
        FOREIGN KEY (IdGanador)
        REFERENCES Usuarios(IdUsuario),

    CONSTRAINT CK_Subastas_MontoBase
        CHECK (MontoBase >= 20000),

    CONSTRAINT CK_Subastas_Estado
        CHECK (
            Estado IN (
                'Pendiente',
                'Activa',
                'Finalizada',
                'Desierta',
                'Cancelada'
            )
        ),

    CONSTRAINT CK_Subastas_Fechas
        CHECK (FechaHoraCierre > FechaHoraInicio)
);


/* =========================================================
   5. PUJAS
   ========================================================= */

CREATE TABLE Pujas (
    IdPuja INT IDENTITY(1,1) PRIMARY KEY,

    IdSubasta INT NOT NULL,

    IdUsuario INT NOT NULL,

    Monto DECIMAL(12,2) NOT NULL,

    FechaHora DATETIME2 NOT NULL DEFAULT GETDATE(),

    CONSTRAINT FK_Pujas_Subastas
        FOREIGN KEY (IdSubasta)
        REFERENCES Subastas(IdSubasta),

    CONSTRAINT FK_Pujas_Usuarios
        FOREIGN KEY (IdUsuario)
        REFERENCES Usuarios(IdUsuario),

    CONSTRAINT CK_Pujas_Monto
        CHECK (Monto > 0)
);


/* =========================================================
   6. NOTIFICACIONES
   OPCIONAL PERO UTIL
   ========================================================= */

CREATE TABLE Notificaciones (
    IdNotificacion INT IDENTITY(1,1) PRIMARY KEY,

    IdUsuario INT NOT NULL,

    IdSubasta INT NULL,

    Mensaje VARCHAR(500) NOT NULL,

    Leida BIT NOT NULL DEFAULT 0,

    FechaHora DATETIME2 NOT NULL DEFAULT GETDATE(),

    CONSTRAINT FK_Notificaciones_Usuarios
        FOREIGN KEY (IdUsuario)
        REFERENCES Usuarios(IdUsuario),

    CONSTRAINT FK_Notificaciones_Subastas
        FOREIGN KEY (IdSubasta)
        REFERENCES Subastas(IdSubasta)
);