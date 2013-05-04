<?php
namespace BMWCCA;

class Client
{
    protected $username;
    protected $password;

    public function __construct($username, $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    public function getMembershipInfo($memberNumber)
    {
        $username = $this->username;
        $password = $this->password;

        $soapClient = new \SoapClient("https://myaccount.bmwcca.org/Qws/Qws.asmx?wsdl");
        $memberData = [];

        $personSearch = $soapClient->SearchObject([
            "BusinessObject" => "Person",
            "Criteria"       => "ConstituentID=eq,$memberNumber",
            "Credentials"    => "$username;$password",
            "Delimiter"      => ";",
            "Fields"         => "",
            "DedupeField"    => "",
            "SortFields"     => "",
            "MaxResults"     => ""
        ]);
        $personSearch = json_decode(json_encode(simplexml_load_string($personSearch->SearchObjectResult->any)), true); // quick way to convert XML to array
        if ($personSearch) {
            // catalog data found so far
            $actualData = $personSearch["Qdst"]["Person"];
            $memberData["first_name"] = $actualData["FirstName"];
            $memberData["last_name"] = $actualData["LastName"];
            $memberData["active_member"] = (boolean) intval($actualData["IsMember"]);

            // continue searching
            $constituentMembershipSearch = $soapClient->SearchObject([
                "BusinessObject" => "ConstituentMembership",
                "Criteria"       => "ConstituentID=eq,$memberNumber",
                "Credentials"    => "$username;$password",
                "Delimiter"      => ";",
                "Fields"         => "",
                "DedupeField"    => "",
                "SortFields"     => "EndDate DESC",
                "MaxResults"     => ""
            ]);
            $constituentMembershipSearch = json_decode(json_encode(simplexml_load_string($constituentMembershipSearch->SearchObjectResult->any)), true);
            if ($constituentMembershipSearch) {
                $actualData = $constituentMembershipSearch["Qdst"]["ConstituentMembership"];
                foreach ($actualData as $membershipItem) {
                    // save some of this data
                    $memberData["join_date"] = $membershipItem["JoinDate"];

                    // get details of each membership item
                    $memberUID = $membershipItem["MemberUID"];
                    $membershipItemSearch = $soapClient->SearchObject([
                        "BusinessObject" => "MemberDetailItem",
                        "Criteria"       => "MemberUID=eq,$memberUID;IsChapter=eq,1",
                        "Credentials"    => "$username;$password",
                        "Delimiter"      => ";",
                        "Fields"         => "",
                        "DedupeField"    => "",
                        "SortFields"     => "",
                        "MaxResults"     => ""
                    ]);
                    $membershipItemSearch = json_decode(json_encode(simplexml_load_string($membershipItemSearch->SearchObjectResult->any)), true);
                    if ($membershipItemSearch) {
                        $data = $membershipItemSearch["Qdst"]["MemberDetailItem"];
                        // save membership data
                        $memberData["memberships"][] = [
                            "chapter" => $data["Description"],
                            "start_date" => $data["StartDate"],
                            "end_date" => $data["EndDate"]
                        ];
                    }
                }
            }
        } else {
            return []; // nothing at all found for that member id
        }
        return $memberData;
    }
}