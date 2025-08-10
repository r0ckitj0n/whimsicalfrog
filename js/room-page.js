/**
 * Initializes the room page by loading data from the DOM and making it available to other scripts.
 * This acts as the bridge between the PHP backend and the client-side room logic.
 */
document.addEventListener('DOMContentLoaded', () => {
  const roomDataContainer = document.getElementById('room-data-container');

  if (roomDataContainer && roomDataContainer.dataset.roomData) {
    try {
      // Parse the data passed from the PHP backend
      const roomData = JSON.parse(roomDataContainer.dataset.roomData);

      // Assign to a global variable for legacy scripts that depend on it
      window.roomData = roomData;

      // Log for successful loading and debugging
      console.log('Room data successfully loaded for room:', roomData.roomNumber);

      // Dispatch a custom event to notify other modules that the room is ready
      // This is a more modern approach than relying on globals.
      document.dispatchEvent(new CustomEvent('roomDataReady', { detail: roomData }));

    } catch (error) {
      console.error('Failed to parse room data:', error);
    }
  } else {
    // This should not happen on a room page, but good to have a check.
    console.warn('Room data container not found. Is this a room page?');
  }
});
