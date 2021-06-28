var models  = require('../models');
var Queue = require('../components/Queue')

export function register(socket) {
  //create new message
  socket.on('new-chat-message', function(body) {    
    //TODO - store to DB then emit to client
    // if isset user save user id else it is anonymous save 0
console.log(body);
    if(socket.user){

      var message = {
        type: body.type,
        ownerId: socket.user.id,
        threadId: body.roomId,
        text: body.text,
        tip: 'no'
      };

      // Queue.create('CREATE_DB', {
      //   model: 'ChatMessage',
      //   data: message
      // }).save();

      Queue.create('CREATE_DB', {
        model: 'ChatMessage',
        data: message,
        flag: true
      }).save();
      socket.broadcast.to(socket.threadId).emit('new-chat-message', {
        message: message,
        text: body.text,
        createdAt: new Date(),
        username: socket.user.username,
        userId: socket.user.id,
        role: socket.user.role,
        vr: body.vr,
        id: body.id
      });
      socket.broadcast.to(body.modelId).emit('new-chat-message-coming', {
        message: message,
        text: body.text,
        createdAt: new Date(),
        username: socket.user.username,
        userId: socket.user.id,
        role: socket.user.role,
        vr: body.vr,
        type: body.type
      });
    }
  });

  socket.on('reset-chat-message', function(body) {    
   socket.broadcast.to(socket.threadId).emit('reset-chat-message', {
      message: 'ok'
    });
  });
  socket.on('remove-chat-message', function(body) {    
   socket.broadcast.to(socket.threadId).emit('remove-chat-message', body);
  });
}
