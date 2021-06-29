let models  = require('../models');
var Queue = require('../components/Queue');

export function register(socket) {
  //create new message
  socket.on('send-tip', function(body) {
    //TODO - store to DB then emit to client
    // if isset user save user id else it is anonymous save 0
    if(!socket.user){
      return;
    }

    var createdAt = new Date();
    var updatedAt = new Date();
    //temp message which will be transfered via socket
    var message = {
      type: body.type,
      tip: 'yes',
      ownerId: socket.user.id,
      threadId: body.roomId,
      text: body.text,
      createdAt: createdAt,
      updatedAt: updatedAt
    };

    Queue.create('CREATE_DB', {
      model: 'ChatMessage',
      data: message
    }).save();

    socket.emit('send-tip', {
      message: message,
      text: body.text,
      createdAt:createdAt,
      username: socket.user.username,
      userId: socket.user.id,
      role: socket.user.role
    });
    socket.broadcast.to(socket.threadId).emit('send-tip', {
      message: message,
      text: body.text,
      createdAt: createdAt,
      username: socket.user.username,
      userId: socket.user.id,
      role: socket.user.role,
      tokens: body.tokens
    });

  });

  socket.on('goal-tip-broadcast', function(tip) {
    //TODO - store to DB then emit to client
    // if isset user save user id else it is anonymous save 0
    if(!socket.user){
      return;
    }
console.log(tip);
    if (socket.user.role === 'model') {
      socket.broadcast.to(socket.threadId).emit('goal-tip-broadcast', tip);
    }

  });
}
