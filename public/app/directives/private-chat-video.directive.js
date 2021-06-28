'use strict';

angular.module('matroshkiApp')
.directive('mPrivateChatVideo', ['appSettings', '$timeout', '$interval', 'socket', 'VideoStream', 'peerService', '$sce', 'userService', 'onlineService', function(appSettings, $timeout, $interval, socket, VideoStream, peerService, $sce, userService, onlineService) {
  return {
    restrict: 'AE',
    templateUrl: appSettings.BASE_URL + 'app/views/partials/private-chat-video-widget.html',
    scope: {
      modelId: '=modelId',
      memberId: '=memberId',
      room: '@',
      virtualRoom: '@',
      streamingInfo : "=ngModel"
    },
    controller: function($scope, socket, userService, PerformerChat, $timeout, $window) {
      $scope.userRole = appSettings.USER.role;
      $scope.streamingInfo.type = 'private';
      $scope.streamingInfo.hasRoom = true;
      $scope.streamingInfo.removeMyRoom = false;
      $scope.second = 60;
      $scope.sendCallRequest = function() {
        socket.emit('checkOnline', $scope.modelId.toString(), function(data) {
          if(!data.isOnline) {
            return alertify.error('Model is now offline');
          }
          //check user token before start connect.
          userService.get().then(function (data) {
            if (data.data) {
              if(parseInt(data.data.tokens) < 1){
                return alertify.error('Credit is finished and chat will end', 6, function() {
                  return endStream();
                })
              } else {
                // open room for streaming
                connection.open($scope.virtualRoom, function(isRoomOpened, roomid, error) {
                  if (isRoomOpened === true) {
                    $scope.userStreaming = true;
                    peerService.joinRoom($scope.virtualRoom, {
                      memberId: $scope.memberId,
                      modelId: $scope.modelId,
                      room: $scope.room
                    });
                  } else {
                    if (error === connection.errors.ROOM_NOT_AVAILABLE) {
                        alert('Someone already created this room. Please either join or create a separate room.');
                        return;
                    }
                    alert(error);
                  }
                });
              }
            } else {
              return false;
            }
          });
        });

      };
      $scope.acceptRequest = function() {
          connection.join($scope.virtualRoom, function(isJoinedRoom, roomid, error) {
              if (error) {
                  if (error === connection.errors.ROOM_NOT_AVAILABLE) {
                      alert('This room does not exist. Please either create it or wait for moderator to enter in the room.');
                      return;
                  }
                  if (error === connection.errors.ROOM_FULL) {
                      alert('Room is full.');
                      return;
                  }
                  alert(error);
                  return;
              }
              peerService.joinRoom($scope.virtualRoom, {
                memberId: $scope.memberId,
                modelId: $scope.modelId,
                room: $scope.room
              });
              $scope.modelStreaming = true;
          });
      };
      var connection = new RTCMultiConnection();
      // maximum two users are allowed to join single room
      connection.maxParticipantsAllowed = 2;
      connection.socketURL = appSettings.RTC_URL;
      connection.socketMessageEvent = 'one-to-one-' + $scope.room;
      connection.session = {
          audio: true,
          video: true
      };
      connection.sdpConstraints.mandatory = {
          OfferToReceiveAudio: true,
          OfferToReceiveVideo: true
      };
      connection.videosContainer = document.getElementById('private-videos-container');
      var intervalPayment = null;
      connection.onstream = function(event) {
        var existing = document.getElementById(event.streamid);
        if (existing && existing.parentNode) {
            existing.parentNode.removeChild(existing);
        }
        event.mediaElement.removeAttribute('src');
        event.mediaElement.removeAttribute('srcObject');
        event.mediaElement.muted = true;
        event.mediaElement.volume = 0;
        var video = document.createElement('video');
        try {
            video.setAttributeNode(document.createAttribute('autoplay'));
            video.setAttributeNode(document.createAttribute('playsinline'));
        } catch (e) {
            video.setAttribute('autoplay', true);
            video.setAttribute('playsinline', true);
        }
        if (event.type === 'local') {
            video.volume = 0;
            try {
                video.setAttributeNode(document.createAttribute('muted'));
            } catch (e) {
                video.setAttribute('muted', true);
            }
        }
        video.srcObject = event.stream;
        video.controls = true;
        video.className = event.type;
        connection.videosContainer.appendChild(video);
        setTimeout(function() {
            video.play();
        }, 5000);
        video.id = event.streamid;
        // to keep room-id in cache
        localStorage.setItem(connection.socketMessageEvent, connection.sessionid);
        if (event.type === 'local') {
            connection.socket.on('disconnect', function() {
                if (!connection.getAllParticipants().length) {
                    location.reload();
                }
            });
        }
        if(connection.getAllParticipants().length > 0 && $scope.userRole === 'member' && !intervalPayment) {
          intervalPayment = $interval(function () {
            if($scope.second === 60){
                $scope.second = 0;
                $scope.streamingInfo.time++;
                sendPaidTokens();
            }
            $scope.second++;
          }, 1000);
        }
      };

      connection.onstreamended = function(event) {
          var mediaElement = document.getElementById(event.streamid);
          if (mediaElement) {
              mediaElement.parentNode.removeChild(mediaElement);
          }
          alertify.message('Chat will end now', 30);
          $timeout(endStream, 6000);
      };
      connection.onMediaError = function(e) {
          if (e.message === 'Concurrent mic process limit.') {
              if (DetectRTC.audioInputDevices.length <= 1) {
                  alert('Please select external microphone. Check github issue number 483.');
                  return;
              }
              var secondaryMic = DetectRTC.audioInputDevices[1].deviceId;
              connection.mediaConstraints.audio = {
                  deviceId: secondaryMic
              };
              document.getElementById('join-room').onclick();
          }
      };
      $scope.stopStreaming = function() {
        if (appSettings.USER && appSettings.USER.role == 'member') {
          socket.emit('stop-video-request',
          {
            data: {
              modelId: $scope.modelId,
            }
          });
        }
        socket.emit('model-leave-room');
        endStream();
      };
      function endStream() {
        if(appSettings.USER.role == 'model'){
          $window.location.href = '/models/live';
        } else {
          $window.location.href = '/';
        }
      }

      function sendPaidTokens() {
        userService.sendPaidTokens($scope.modelId, 'private').then(function (response)
        {
          if(response.data && parseInt(response.data.spend) > 0){
              $scope.streamingInfo.spendTokens += parseInt(response.data.spend);
              $scope.streamingInfo.tokens = response.data.tokens;
              socket.sendModelReceiveInfo({time: 1, tokens: response.data.spend});
          }
          if (response.data.success == false) {
             socket.emit('member-missing-tokens', $scope.chatType);
             return alertify.error('Credit is finished and chat will end', 6, endStream);
          }
        });
      }

      // show full screen
      $scope.isFullScreenMode = false;
      $scope.showFullScreen = function() {
          $scope.isFullScreenMode = true;
          $('.header').addClass('hidden');
          $('.line-menu').addClass('hidden');
          $('.footer').addClass('hidden');
          $('body').addClass('fullscreen-mode');
          $('.panel-heading').addClass('hidden');
          $('.private-chat-instruction').addClass('hidden');
          $scope.isFullScreenMode = true;
      };
      $scope.notShowFullScreen = function() {
          $scope.isFullScreenMode = false;
          $('.header').removeClass('hidden');
          $('.line-menu').removeClass('hidden');
          $('.footer').removeClass('hidden');
          $('body').removeClass('fullscreen-mode');
          $('.panel-heading').removeClass('hidden');
          $('.private-chat-instruction').removeClass('hidden');
      };
    }
  };
}]);