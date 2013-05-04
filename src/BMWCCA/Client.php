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
        $this->soapClient = new \SoapClient("https://myaccount.bmwcca.org/Qws/Qws.asmx?wsdl");

    }

    public function getMembershipInfo($memberNumber)
    {
        $username = $this->username;
        $password = $this->password;
        $soapClient = $this->soapClient;

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

            $details = $this->getDetailsForConstituent($memberNumber);
            $memberData["join_date"] = $details["join_date"];
            $memberData["memberships"] = $details["memberships"];

            // search for associates
            $associateSearch = $soapClient->SearchObject([
                "BusinessObject" => "Person",
                "Criteria"       => "ConstituentID=ne,a;ParentID=eq,$memberNumber",
                "Credentials"    => "$username;$password",
                "Delimiter"      => ";",
                "Fields"         => "",
                "DedupeField"    => "",
                "SortFields"     => "",
                "MaxResults"     => ""
            ]);
            $associateSearch = json_decode(json_encode(simplexml_load_string($associateSearch->SearchObjectResult->any)), true);
            $associateData = $associateSearch["Qdst"]["Person"];
            if ($associateSearch) {
                $memberData["associates"] = $this->getMembershipInfo($associateData["ConstituentID"]);
            }
        } else {
            return []; // nothing at all found for that member id
        }
        return $memberData;
    }

    private function getDetailsForConstituent($constituentId)
    {
        $username = $this->username;
        $password = $this->password;
        $soapClient = $this->soapClient;

        $memberData = [];

        // continue searching
        $constituentMembershipSearch = $soapClient->SearchObject([
            "BusinessObject" => "ConstituentMembership",
            "Criteria"       => "ConstituentID=eq,$constituentId",
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
        return $memberData;
    }
}