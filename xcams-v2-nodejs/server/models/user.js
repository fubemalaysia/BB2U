'use strict';

module.exports = function(sequelize, DataTypes) {
  var User = sequelize.define('User', {
    username: DataTypes.STRING,
    firstName: DataTypes.STRING,
    lastName: DataTypes.STRING,
    email: DataTypes.STRING,
    emailVerified: {
      type: DataTypes.BLOB('tiny'),
      allowNull: false,
      defaultValue: 0
    },
    emailVerifyToken: DataTypes.STRING,
    passwordHash: DataTypes.STRING,
    passwordResetToken: DataTypes.STRING,
    gender: DataTypes.ENUM('male', 'female'),
    birthdate: DataTypes.DATE,
    role: DataTypes.ENUM('menber', 'model', 'studio'),
    avatar: DataTypes.STRING,
    parentId: DataTypes.INTEGER,
    status: DataTypes.STRING
  }, {
    classMethods: {
    },
    tableName: 'users'
  });

  return User;
};