var tweetUsers = ['_davidhudson', 'locatoraderules'];
var buildString = "";

$(document).ready(function(){
	for(var i=0;i<tweetUsers.length;i++) {
		if(i!=0) buildString+='+OR+';
		buildString+='from:'+tweetUsers[i];
	}
	
	var fileref = document.createElement('script');
	
	fileref.setAttribute("type","text/javascript");
	fileref.setAttribute("src", "http://search.twitter.com/search.json?q="+buildString+"&callback=ShowTweets&rpp=50");
	
	document.getElementsByTagName("head")[0].appendChild(fileref);
});

function ShowTweets(ob)
{
	var container=$('#tweet-container');
	container.html('');
	
	$(ob.results).each(function(el){
		var str = '<div class="locatorade_tweet">\
					<div class="locatorade_avatar"><a href="http://twitter.com/'+this.from_user+'" target="_blank"><img src="'+this.profile_image_url+'" alt="'+this.from_user+'" /></a></div>\
					<div class="locatorade_tweet_info"><div class="locatorade_user"><a href="http://twitter.com/'+this.from_user+'" target="_blank">'+this.from_user+'</a></div>\
					<div class="locatorade_time">'+relativeTime(this.created_at)+'</div>\
					<div class="locatorade_txt">'+formatTwitString(this.text)+'</div></div>\
					</div>';
		
		container.append(str);
	});
}

function formatTwitString(str) {
	str=' '+str;
	str = str.replace(/((ftp|https?):\/\/([-\w\.]+)+(:\d+)?(\/([\w/_\.]*(\?\S+)?)?)?)/gm,'<a href="$1" target="_blank">$1</a>');
	str = str.replace(/([^\w])\@([\w\-]+)/gm,'$1@<a href="http://twitter.com/$2" target="_blank">$2</a>');
	str = str.replace(/([^\w])\#([\w\-]+)/gm,'$1<a href="http://twitter.com/search?q=%23$2" target="_blank">#$2</a>');
	return str;
}

function relativeTime(pastTime) {	
	var origStamp = Date.parse(pastTime);
	var curDate = new Date();
	var currentStamp = curDate.getTime();
	
	var difference = parseInt((currentStamp - origStamp)/1000);

	if(difference < 0) return false;

	if(difference <= 5)				return "Just now";
	if(difference <= 20)			return "Seconds ago";
	if(difference <= 60)			return "A minute ago";
	if(difference < 3600)			return parseInt(difference/60)+" minutes ago";
	if(difference <= 1.5*3600) 		return "One hour ago";
	if(difference < 23.5*3600)		return Math.round(difference/3600)+" hours ago";
	if(difference < 1.5*24*3600)	return "One day ago";
	
	var dateArr = pastTime.split(' ');
	return dateArr[4].replace(/\:\d+$/,'')+' '+dateArr[2]+' '+dateArr[1]+(dateArr[3]!=curDate.getFullYear()?' '+dateArr[3]:'');
}