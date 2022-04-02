'use strict';



function pollRescaleTracks() {

    // summarize points
    let points_sum = 0;
    for (let input_id of PointsTrackId) {
        let input = document.getElementById(input_id);
        points_sum += parseInt(input.value);
    }

    // rescale points
    if (points_sum > PointsForTracks) {
        let points_sum_new = parseInt(this.value);
        for (let input_id of PointsTrackId) {
            if (input_id == this.id) continue;
            let input = document.getElementById(input_id);
            input.value = Math.floor(parseInt(input.value) * PointsForTracks / points_sum);
            points_sum_new += parseInt(input.value);
        }
        points_sum = points_sum_new;
    }

    // update points left to vote
    pollSetPointTrackLeft(PointsForTracks - points_sum);
}


function pollSetPointTrackLeft(points) {
    let points_left = document.getElementById('PointsTrackLeftToVote');
    if (points_left) {
        points_left.textContent = points;
        if (points == 0) {
            points_left.className = "PollPointsEmpty";
        } else {
            points_left.className = "PollPointsAvailable";
        }
    }
}



function pollRescaleCarClasses() {

    // summarize points
    let points_sum = 0;
    for (let input_id of PointsCarClassId) {
        let input = document.getElementById(input_id);
        points_sum += parseInt(input.value);
    }

    // rescale points
    if (points_sum > PointsForCarClasses) {
        let points_sum_new = parseInt(this.value);
        for (let input_id of PointsCarClassId) {
            if (input_id == this.id) continue;
            let input = document.getElementById(input_id);
            input.value = Math.floor(parseInt(input.value) * PointsForCarClasses / points_sum);
            points_sum_new += parseInt(input.value);
        }
        points_sum = points_sum_new;
    }

    // update points left to vote
    pollSetPointCarClassLeft(PointsForCarClasses - points_sum);
}



function pollSetPointCarClassLeft(points) {
    let points_left = document.getElementById('PointsCarClassLeftToVote');
    if (points_left) {
        points_left.textContent = points;
        if (points == 0) {
            points_left.className = "PollPointsEmpty";
        } else {
            points_left.className = "PollPointsAvailable";
        }
    }
}



document.addEventListener('DOMContentLoaded', function () {

    // event listener for tracks
    let points_sum = 0;
    for (let input_id of PointsTrackId) {
        let input = document.getElementById(input_id);
        input.addEventListener("input", pollRescaleTracks);
        points_sum += parseInt(input.value);
    }
    pollSetPointTrackLeft(PointsForTracks - points_sum);


    // event listener for carclasses
    points_sum = 0;
    for (let input_id of PointsCarClassId) {
        let input = document.getElementById(input_id);
        input.addEventListener("input", pollRescaleCarClasses);
        points_sum += parseInt(input.value);
    }
    pollSetPointCarClassLeft(PointsForCarClasses - points_sum);

});
